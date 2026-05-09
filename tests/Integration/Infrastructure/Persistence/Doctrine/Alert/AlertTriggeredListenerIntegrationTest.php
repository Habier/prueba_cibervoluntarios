<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Alert;

use App\DataFixtures\FixtureIds;
use App\Domain\Alert\AlertDraft;
use App\Domain\Alert\AlertSeverity;
use App\Domain\Gps\Event\AlertTriggered;
use App\Domain\Gps\GpsCoordinate;
use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;
use App\Domain\Vehicle\ValueObject\VehicleId;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AlertTriggeredListenerIntegrationTest extends DatabaseTestCase
{
    public function testAlertTriggeredEventPersistsAlertSynchronously(): void
    {
        static::createClient();
        $container = static::getContainer();

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $observation = new GpsCoordinate(
            new VehicleId(FixtureIds::VEHICLE_1),
            new Latitude(40.0),
            new Longitude(-3.0),
            new Speed(132.0),
            new DeviceTimestamp('2026-01-04T00:00:00+00:00'),
            new \DateTimeImmutable('2026-01-04T00:00:01+00:00'),
            'listener-alert-1',
            10.0,
            5.0,
        );

        $eventDispatcher->dispatch(new AlertTriggered(
            $observation,
            new AlertDraft(
                $observation->vehicleId,
                'SPEED_EXCEEDED',
                'Speed exceeded 120 km/h',
                AlertSeverity::HIGH,
            ),
        ));

        self::assertSame($initialAlertCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts'));
    }

    public function testUnknownAlertTypeCodeIsIgnoredByListener(): void
    {
        static::createClient();
        $container = static::getContainer();

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $initialAlertCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts');

        $observation = new GpsCoordinate(
            new VehicleId(FixtureIds::VEHICLE_1),
            new Latitude(40.0),
            new Longitude(-3.0),
            new Speed(132.0),
            new DeviceTimestamp('2026-01-04T00:10:00+00:00'),
            new \DateTimeImmutable('2026-01-04T00:10:01+00:00'),
            'listener-alert-2',
            10.0,
            5.0,
        );

        $eventDispatcher->dispatch(new AlertTriggered(
            $observation,
            new AlertDraft(
                $observation->vehicleId,
                'UNKNOWN_ALERT',
                'Unknown alert should be skipped',
                AlertSeverity::LOW,
            ),
        ));

        self::assertSame($initialAlertCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM alerts'));
    }
}
