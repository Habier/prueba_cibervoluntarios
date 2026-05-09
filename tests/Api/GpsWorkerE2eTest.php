<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\FixtureIds;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqGpsMessagePublisher;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use App\Infrastructure\Worker\GpsMessageConsumer;
use App\Tests\Double\InMemoryGpsMessagePublisher;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Group('e2e')]
final class GpsWorkerE2eTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        $this->configureInfrastructureEnv();

        parent::setUp();
    }

    #[WithoutErrorHandler]
    public function testWorkerConsumesHundredsOfRealisticCoordinatesFromRabbitMq(): void
    {
        // End-to-end async contract: publish -> queue -> consume -> persist,
        // using real RabbitMQ services (not in-memory doubles).
        static::createClient();
        $container = static::getContainer();
        $this->purgeQueues($container);
        $this->resetInMemoryPublisherIfAvailable($container);
        $publisher = $this->rabbitMqPublisher($container);

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        /** @var GpsMessageConsumer $consumer */
        $consumer = $container->get(GpsMessageConsumer::class);
        $initialGpsCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates');
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $totalCoordinates = 300;
        $coordinatesPerRequest = 20;
        $requestCount = (int) ceil($totalCoordinates / $coordinatesPerRequest);

        // Expected alerts by type
        $expectedSpeedExceededAlerts = 0;
        $expectedGeofenceBreachAlerts = 0;
        $expectedIdleAlerts = 0;

        try {
            for ($requestIndex = 0; $requestIndex < $requestCount; ++$requestIndex) {
                $payload = [
                    'coordinates' => [],
                ];

                for ($coordinateIndex = 0; $coordinateIndex < $coordinatesPerRequest && (($requestIndex * $coordinatesPerRequest) + $coordinateIndex) < $totalCoordinates; ++$coordinateIndex) {
                    $sequence = ($requestIndex * $coordinatesPerRequest) + $coordinateIndex;

                    // 0-99 speed, 100-199 geofence, 200-299 idle.
                    $scenario = intdiv($sequence, 100);
                    $speedKmh = 78.0;
                    $latitude = 40.4168 + (($sequence % 10) * 0.001);
                    $longitude = -3.7038 - (($sequence % 10) * 0.001);

                    if ($scenario === 0) {
                        $speedKmh = ($sequence % 50) === 0 ? 132.0 : 88.0 + ($sequence % 12);
                        if ($speedKmh > 120) {
                            ++$expectedSpeedExceededAlerts;
                        }
                    } elseif ($scenario === 1) {
                        // Force out-of-bounds latitude for geofence alerts.
                        $latitude = 40.2 + (($sequence % 10) * 0.001);
                        ++$expectedGeofenceBreachAlerts;
                    } else {
                        // Keep speed below idle threshold (0.5 km/h).
                        $speedKmh = 0.1 + (($sequence % 5) * 0.08);
                        ++$expectedIdleAlerts;
                    }

                    $payload['coordinates'][] = [
                        'externalId' => sprintf('e2e-%03d', $sequence),
                        'vehicleId' => match ($sequence % 3) {
                            0 => FixtureIds::VEHICLE_1,
                            1 => FixtureIds::VEHICLE_2,
                            default => FixtureIds::VEHICLE_3,
                        },
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'altitude' => 20 + ($sequence % 50),
                        'speedKmh' => $speedKmh,
                        'accuracy' => 3 + ($sequence % 4),
                        'deviceTimestamp' => (new \DateTimeImmutable('2026-01-02T08:00:00+00:00'))->modify(sprintf('+%d seconds', $sequence))->format(DATE_ATOM),
                    ];
                }

                foreach ($payload['coordinates'] as $coordinatePayload) {
                    $publisher->publish([
                        ...$coordinatePayload,
                        'receivedAt' => '2026-01-02T08:10:00+00:00',
                    ]);
                }
            }

            self::assertSame(0, $this->inMemoryPublisherMessageCount($container), 'In-memory publisher should not be used in RabbitMQ e2e path.');

            $consumer->consume(maxIdleTimeouts: 3);

            self::assertSame($initialGpsCount + $totalCoordinates, (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates'));

            $finalAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');
            $speedExceededCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts a JOIN alert_types t ON t.id = a.alert_type_id WHERE t.code = ?', ['SPEED_EXCEEDED']);
            $geofenceCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts a JOIN alert_types t ON t.id = a.alert_type_id WHERE t.code = ?', ['GEOFENCE_BREACH']);
            $idleCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts a JOIN alert_types t ON t.id = a.alert_type_id WHERE t.code = ?', ['IDLE_TOO_LONG']);

            self::assertGreaterThanOrEqual($expectedSpeedExceededAlerts, $speedExceededCount, 'Should have speed exceeded alerts');
            self::assertGreaterThan(0, $geofenceCount, 'Should have geofence breach alerts');
            self::assertGreaterThan(0, $idleCount, 'Should have idle alerts');

            self::assertSame(0, $this->countQueueMessages($container, $this->rabbitMqConfig($container)->queue));
            self::assertSame(0, $this->countQueueMessages($container, $this->rabbitMqConfig($container)->dlqQueue));
        } finally {
            $this->purgeQueues($container);
        }
    }

    private function configureInfrastructureEnv(): void
    {
        $rabbitMqHost = $this->detectRabbitMqHost();
        $databaseHost = $this->detectDatabaseHost();
        $databasePort = $databaseHost === 'database' ? '5432' : '55432';
        $testDatabaseUrl = sprintf('postgresql://app:app@%s:%s/app_test?serverVersion=16&charset=utf8', $databaseHost, $databasePort);
        $testExchange = 'gps.coordinates.e2e.test';
        $testQueue = 'gps.coordinates.queue.e2e.test';
        $testRoutingKey = 'gps.coordinates.ingest.e2e.test';
        $testDlqExchange = 'gps.coordinates.dlq.e2e.test';
        $testDlqQueue = 'gps.coordinates.queue.dlq.e2e.test';
        $testDlqRoutingKey = 'gps.coordinates.dlq.e2e.test';

        putenv(sprintf('RABBITMQ_HOST=%s', $rabbitMqHost));
        putenv('RABBITMQ_PORT=5672');
        putenv('RABBITMQ_USER=guest');
        putenv('RABBITMQ_PASSWORD=guest');
        putenv('RABBITMQ_VHOST=/');
        putenv(sprintf('RABBITMQ_GPS_EXCHANGE=%s', $testExchange));
        putenv(sprintf('RABBITMQ_GPS_QUEUE=%s', $testQueue));
        putenv(sprintf('RABBITMQ_GPS_ROUTING_KEY=%s', $testRoutingKey));
        putenv(sprintf('RABBITMQ_GPS_DLQ_EXCHANGE=%s', $testDlqExchange));
        putenv(sprintf('RABBITMQ_GPS_DLQ_QUEUE=%s', $testDlqQueue));
        putenv(sprintf('RABBITMQ_GPS_DLQ_ROUTING_KEY=%s', $testDlqRoutingKey));
        putenv(sprintf('TEST_DATABASE_URL=%s', $testDatabaseUrl));

        $_SERVER['RABBITMQ_HOST'] = $rabbitMqHost;
        $_ENV['RABBITMQ_HOST'] = $rabbitMqHost;
        $_SERVER['RABBITMQ_PORT'] = '5672';
        $_ENV['RABBITMQ_PORT'] = '5672';
        $_SERVER['RABBITMQ_USER'] = 'guest';
        $_ENV['RABBITMQ_USER'] = 'guest';
        $_SERVER['RABBITMQ_PASSWORD'] = 'guest';
        $_ENV['RABBITMQ_PASSWORD'] = 'guest';
        $_SERVER['RABBITMQ_VHOST'] = '/';
        $_ENV['RABBITMQ_VHOST'] = '/';
        $_SERVER['RABBITMQ_GPS_EXCHANGE'] = $testExchange;
        $_ENV['RABBITMQ_GPS_EXCHANGE'] = $testExchange;
        $_SERVER['RABBITMQ_GPS_QUEUE'] = $testQueue;
        $_ENV['RABBITMQ_GPS_QUEUE'] = $testQueue;
        $_SERVER['RABBITMQ_GPS_ROUTING_KEY'] = $testRoutingKey;
        $_ENV['RABBITMQ_GPS_ROUTING_KEY'] = $testRoutingKey;
        $_SERVER['RABBITMQ_GPS_DLQ_EXCHANGE'] = $testDlqExchange;
        $_ENV['RABBITMQ_GPS_DLQ_EXCHANGE'] = $testDlqExchange;
        $_SERVER['RABBITMQ_GPS_DLQ_QUEUE'] = $testDlqQueue;
        $_ENV['RABBITMQ_GPS_DLQ_QUEUE'] = $testDlqQueue;
        $_SERVER['RABBITMQ_GPS_DLQ_ROUTING_KEY'] = $testDlqRoutingKey;
        $_ENV['RABBITMQ_GPS_DLQ_ROUTING_KEY'] = $testDlqRoutingKey;
        $_SERVER['TEST_DATABASE_URL'] = $testDatabaseUrl;
        $_ENV['TEST_DATABASE_URL'] = $testDatabaseUrl;
    }

    private function rabbitMqPublisher(ContainerInterface $container): RabbitMqGpsMessagePublisher
    {
        return new RabbitMqGpsMessagePublisher(
            $this->rabbitMqConnectionFactory($container),
            $this->rabbitMqTopologyManager($container),
            $this->rabbitMqConfig($container),
        );
    }

    private function purgeQueues(ContainerInterface $container): void
    {
        try {
            $connection = $this->rabbitMqConnectionFactory($container)->create();
        } catch (\Throwable $throwable) {
            self::markTestSkipped(sprintf('RabbitMQ is not available for e2e test: %s', $throwable->getMessage()));
        }

        $channel = $connection->channel();
        $config = $this->rabbitMqConfig($container);
        $this->rabbitMqTopologyManager($container)->declare($channel);
        $channel->queue_purge($config->queue);
        $channel->queue_purge($config->dlqQueue);
        $channel->close();
        $connection->close();
    }

    private function countQueueMessages(ContainerInterface $container, string $queue): int
    {
        $connection = $this->rabbitMqConnectionFactory($container)->create();
        $channel = $connection->channel();
        /** @var array{0:string,1:int} $result */
        $result = $channel->queue_declare($queue, true, true, false, false);
        [, $messageCount] = $result;
        $channel->close();
        $connection->close();

        return (int) $messageCount;
    }

    private function rabbitMqConnectionFactory(ContainerInterface $container): RabbitMqConnectionFactory
    {
        /** @var RabbitMqConnectionFactory $factory */
        $factory = $container->get(RabbitMqConnectionFactory::class);

        return $factory;
    }

    private function resetInMemoryPublisherIfAvailable(ContainerInterface $container): void
    {
        if (! $container->has(InMemoryGpsMessagePublisher::class)) {
            return;
        }

        $publisher = $container->get(InMemoryGpsMessagePublisher::class);
        if (! $publisher instanceof InMemoryGpsMessagePublisher) {
            return;
        }

        $publisher->reset();
    }

    private function inMemoryPublisherMessageCount(ContainerInterface $container): int
    {
        if (! $container->has(InMemoryGpsMessagePublisher::class)) {
            return 0;
        }

        $publisher = $container->get(InMemoryGpsMessagePublisher::class);
        if (! $publisher instanceof InMemoryGpsMessagePublisher) {
            return 0;
        }

        return count($publisher->messages);
    }

    private function rabbitMqTopologyManager(ContainerInterface $container): RabbitMqTopologyManager
    {
        /** @var RabbitMqTopologyManager $topologyManager */
        $topologyManager = $container->get(RabbitMqTopologyManager::class);

        return $topologyManager;
    }

    private function rabbitMqConfig(ContainerInterface $container): RabbitMqConfig
    {
        /** @var RabbitMqConfig $config */
        $config = $container->get(RabbitMqConfig::class);

        return $config;
    }

    private function detectRabbitMqHost(): string
    {
        return gethostbyname('rabbitmq') !== 'rabbitmq' ? 'rabbitmq' : '127.0.0.1';
    }

    private function detectDatabaseHost(): string
    {
        return gethostbyname('database') !== 'database' ? 'database' : '127.0.0.1';
    }
}
