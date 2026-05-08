<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Gps;

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

final class DoctrineGpsBatchPersisterIntegrationTest extends DatabaseTestCase
{
    public function testIdempotencyPreventsDuplicates(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)]);
        $coordinate = new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(40.0), new Longitude(-3.0), new Speed(130.0), new DeviceTimestamp('2026-01-01T00:00:00+00:00'), new \DateTimeImmutable('2026-01-01T00:00:01+00:00'), 'dedupe-1', 10.0, 5.0);
        $initialGpsCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates');
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $persister->persist([$coordinate]);
        $persister->persist([$coordinate]);

        self::assertSame($initialGpsCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates'));
        self::assertSame($initialAlertCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts'));
    }

    public function testOlderDeviceTimestampDoesNotOverrideLastPosition(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)]);

        $newestCoordinate = new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(40.2), new Longitude(-3.2), new Speed(90.0), new DeviceTimestamp('2026-01-01T00:00:10+00:00'), new \DateTimeImmutable('2026-01-01T00:00:11+00:00'), 'ext-newest-1', 10.0, 5.0);
        $olderCoordinate = new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(40.1), new Longitude(-3.1), new Speed(80.0), new DeviceTimestamp('2026-01-01T00:00:01+00:00'), new \DateTimeImmutable('2026-01-01T00:00:12+00:00'), 'ext-older-1', 10.0, 5.0);

        $persister->persist([$newestCoordinate]);
        $persister->persist([$olderCoordinate]);

        $lastPosition = $connection->fetchAssociative('SELECT latitude, longitude, device_timestamp FROM vehicle_last_positions WHERE vehicle_id = :vehicleId', [
            'vehicleId' => FixtureIds::VEHICLE_1,
        ]);

        self::assertNotFalse($lastPosition);
        self::assertSame(40.2, (float) $lastPosition['latitude']);
        self::assertSame(-3.2, (float) $lastPosition['longitude']);
        self::assertSame('2026-01-01 00:00:10+00', (string) $lastPosition['device_timestamp']);
    }

    public function testMultipleCoordinatesUpdateVehicleLastPositionsForEachVehicle(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)]);

        $persister->persist([
            new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(41.0), new Longitude(-3.0), new Speed(90.0), new DeviceTimestamp('2026-01-02T00:00:10+00:00'), new \DateTimeImmutable('2026-01-02T00:00:11+00:00'), 'ext-v1', 10.0, 5.0),
            new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_2), new Latitude(42.0), new Longitude(-4.0), new Speed(70.0), new DeviceTimestamp('2026-01-02T00:00:20+00:00'), new \DateTimeImmutable('2026-01-02T00:00:21+00:00'), 'ext-v2', 12.0, 4.0),
        ]);

        $rows = $connection->fetchAllAssociative('SELECT vehicle_id, latitude, longitude, device_timestamp FROM vehicle_last_positions WHERE vehicle_id IN (?, ?) ORDER BY vehicle_id ASC', [
            FixtureIds::VEHICLE_1,
            FixtureIds::VEHICLE_2,
        ]);

        self::assertCount(2, $rows);
        self::assertSame(FixtureIds::VEHICLE_1, (string) $rows[0]['vehicle_id']);
        self::assertSame(41.0, (float) $rows[0]['latitude']);
        self::assertSame(FixtureIds::VEHICLE_2, (string) $rows[1]['vehicle_id']);
        self::assertSame(42.0, (float) $rows[1]['latitude']);
    }

    public function testMultipleAlertsInOneBatchAreInserted(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)]);
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $persister->persist([
            new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(40.0), new Longitude(-3.0), new Speed(130.0), new DeviceTimestamp('2026-01-03T00:00:10+00:00'), new \DateTimeImmutable('2026-01-03T00:00:11+00:00'), 'alert-1', 10.0, 5.0),
            new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_2), new Latitude(40.1), new Longitude(-3.1), new Speed(140.0), new DeviceTimestamp('2026-01-03T00:00:12+00:00'), new \DateTimeImmutable('2026-01-03T00:00:13+00:00'), 'alert-2', 10.0, 5.0),
        ]);

        self::assertSame($initialAlertCount + 2, (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts'));
    }

    public function testNoAlertsAreInsertedWhenNoRuleMatches(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)]);
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $persister->persist([
            new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_1), new Latitude(40.0), new Longitude(-3.0), new Speed(80.0), new DeviceTimestamp('2026-01-03T00:00:10+00:00'), new \DateTimeImmutable('2026-01-03T00:00:11+00:00'), 'no-alert-1', 10.0, 5.0),
            new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_2), new Latitude(40.1), new Longitude(-3.1), new Speed(90.0), new DeviceTimestamp('2026-01-03T00:00:12+00:00'), new \DateTimeImmutable('2026-01-03T00:00:13+00:00'), 'no-alert-2', 10.0, 5.0),
        ]);

        self::assertSame($initialAlertCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts'));
    }
}
