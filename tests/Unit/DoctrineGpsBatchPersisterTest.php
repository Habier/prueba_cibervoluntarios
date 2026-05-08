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

final class DoctrineGpsBatchPersisterTest extends DatabaseTestCase
{
    public function testIdempotencyPreventsDuplicates(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $persister = new DoctrineGpsBatchPersister($connection, [new SpeedExceededRule(120)]);
        $coordinate = new GpsCoordinate(new VehicleId(FixtureIds::VEHICLE_ALPHA), new Latitude(40.0), new Longitude(-3.0), new Speed(130.0), new DeviceTimestamp('2026-01-01T00:00:00+00:00'), new \DateTimeImmutable('2026-01-01T00:00:01+00:00'), 'dedupe-1', 10.0, 5.0);
        $initialGpsCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates');
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $persister->persist([$coordinate]);
        $persister->persist([$coordinate]);

        self::assertSame($initialGpsCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM gps_coordinates'));
        self::assertSame($initialAlertCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts'));
    }
}
