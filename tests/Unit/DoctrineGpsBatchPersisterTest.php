<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DataFixtures\FixtureIds;
use App\Domain\Alert\SpeedExceededRule;
use App\Domain\Gps\GpsCoordinate;
use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;
use App\Domain\Vehicle\ValueObject\VehicleId;
use App\Infrastructure\Persistence\Doctrine\Gps\DoctrineGpsBatchPersister;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class DoctrineGpsBatchPersisterTest extends DatabaseTestCase
{
    public function testIdempotencyPreventsDuplicates(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)], $this->createMock(LoggerInterface::class));
        $coordinate = new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(40.0), new Longitude(-3.0), new Speed(130.0), new DeviceTimestamp('2026-01-01T00:00:00+00:00'), new \DateTimeImmutable('2026-01-01T00:00:01+00:00'), 'dedupe-1', 10.0, 5.0);
        $initialGpsCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates');
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $persister->persist([$coordinate]);
        $persister->persist([$coordinate]);

        self::assertSame($initialGpsCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates'));
        self::assertSame($initialAlertCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts'));
    }

    public function testUnknownVehiclesAreLoggedAndIgnored(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Unknown vehicle GPS coordinate ignored.',
                self::callback(static function (array $context): bool {
                    self::assertSame('gps.unknown_vehicle_detected', $context['event']);
                    self::assertSame('12121212-1212-4121-8121-121212121212', $context['vehicle_id']);
                    self::assertSame(['ext-unknown-1'], $context['external_ids']);
                    self::assertSame('2026-01-01T00:00:05+00:00', $context['device_timestamp']);
                    self::assertSame(1, $context['unknown_count_for_vehicle']);
                    self::assertSame(1, $context['unknown_count_in_batch']);
                    self::assertSame(2, $context['batch_size']);
                    self::assertSame(1, $context['known_vehicle_count_in_batch']);

                    return true;
                }),
            );

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)], $logger);

        $knownCoordinate = new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(40.0), new Longitude(-3.0), new Speed(90.0), new DeviceTimestamp('2026-01-01T00:00:00+00:00'), new \DateTimeImmutable('2026-01-01T00:00:01+00:00'), 'ext-known-1', 10.0, 5.0);
        $unknownCoordinate = new GpsCoordinate(new VehicleId('12121212-1212-4121-8121-121212121212'), new Latitude(41.0), new Longitude(-4.0), new Speed(70.0), new DeviceTimestamp('2026-01-01T00:00:05+00:00'), new \DateTimeImmutable('2026-01-01T00:00:06+00:00'), 'ext-unknown-1', 12.0, 6.0);

        $initialGpsCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates');
        $persister->persist([$knownCoordinate, $unknownCoordinate]);

        self::assertSame($initialGpsCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates'));
    }
}
