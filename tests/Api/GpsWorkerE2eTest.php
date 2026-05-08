<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Application\Port\GpsMessagePublisherInterface;
use App\DataFixtures\FixtureIds;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqGpsMessagePublisher;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use App\Infrastructure\Worker\GpsMessageConsumer;
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
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $this->swapTestPublisherToRabbitMq($container);
        $this->purgeQueues($container);

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        /** @var GpsMessageConsumer $consumer */
        $consumer = $container->get(GpsMessageConsumer::class);
        $initialGpsCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates');
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $totalCoordinates = 300;
        $coordinatesPerRequest = 20;
        $requestCount = (int) ceil($totalCoordinates / $coordinatesPerRequest);
        
        // Track expected alerts by type
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
                    
                    // Distribute 300 coords across three alert scenarios:
                    // 0-99: Speed exceeded (sequence % 100 < 50 triggers alert)
                    // 100-199: Geofence breach (sequence % 100 between 50-99 triggers alert)
                    // 200-299: Idle (sequence % 100 between 0-49 triggers alert via low speed)
                    $scenario = ($sequence / 100) | 0; // Which 100-block are we in
                    $speedKmh = 78.0;
                    $latitude = 40.4168 + (($sequence % 10) * 0.001);
                    $longitude = -3.7038 - (($sequence % 10) * 0.001);

                    // Generate different scenarios
                    if ($scenario === 0) {
                        // Scenario 1: Speed exceeded
                        $speedKmh = ($sequence % 50) === 0 ? 132.0 : 88.0 + ($sequence % 12);
                        if ($speedKmh > 120) {
                            ++$expectedSpeedExceededAlerts;
                        }
                    } elseif ($scenario === 1) {
                        // Scenario 2: Geofence breach (coordinates outside predefined bounds)
                        // Use Madrid bounds: min_lat 40.3, max_lat 40.5, min_lon -3.8, max_lon -3.5
                        // So use coordinates outside this range
                        $latitude = 40.2 + (($sequence % 10) * 0.001); // Below minimum
                        ++$expectedGeofenceBreachAlerts;
                    } else {
                        // Scenario 3: Idle (very low speed)
                        $speedKmh = 0.1 + (($sequence % 5) * 0.08); // All below 0.5 km/h threshold
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

                $client->request('POST', '/api/gps-coordinates/batch', server: [
                    'CONTENT_TYPE' => 'application/json',
                ], content: (string) json_encode($payload, JSON_THROW_ON_ERROR));

                self::assertResponseStatusCodeSame(202);
                $response = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
                self::assertSame(count($payload['coordinates']), $response['accepted']);
            }

            $consumer->consume(maxIdleTimeouts: 3);

            // Verify all coordinates were processed
            self::assertSame($initialGpsCount + $totalCoordinates, (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates'));
            
            // Verify alert counts by type
            $finalAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');
            $speedExceededCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts a JOIN alert_types t ON t.id = a.alert_type_id WHERE t.code = ?', ['SPEED_EXCEEDED']);
            $geofenceCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts a JOIN alert_types t ON t.id = a.alert_type_id WHERE t.code = ?', ['GEOFENCE_BREACH']);
            $idleCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts a JOIN alert_types t ON t.id = a.alert_type_id WHERE t.code = ?', ['IDLE_TOO_LONG']);
            
            // Assert we have at least one of each alert type
            self::assertGreaterThanOrEqual($expectedSpeedExceededAlerts, $speedExceededCount, 'Should have speed exceeded alerts');
            self::assertGreaterThan(0, $geofenceCount, 'Should have geofence breach alerts');
            self::assertGreaterThan(0, $idleCount, 'Should have idle alerts');
            
            // Verify queue is empty
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

        putenv(sprintf('RABBITMQ_HOST=%s', $rabbitMqHost));
        putenv('RABBITMQ_PORT=5672');
        putenv('RABBITMQ_USER=guest');
        putenv('RABBITMQ_PASSWORD=guest');
        putenv('RABBITMQ_VHOST=/');
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
        $_SERVER['TEST_DATABASE_URL'] = $testDatabaseUrl;
        $_ENV['TEST_DATABASE_URL'] = $testDatabaseUrl;
    }

    private function swapTestPublisherToRabbitMq(ContainerInterface $container): void
    {
        $publisher = new RabbitMqGpsMessagePublisher(
            $this->rabbitMqConnectionFactory($container),
            $this->rabbitMqTopologyManager($container),
            $this->rabbitMqConfig($container),
        );
        $container->set(GpsMessagePublisherInterface::class, $publisher);
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
        [, $messageCount] = $channel->queue_declare($queue, true, true, false, false);
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
