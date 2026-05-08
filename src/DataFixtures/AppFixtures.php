<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Alert\AlertSeverity;
use App\Domain\Vehicle\VehicleStatus;
use App\Infrastructure\Persistence\Doctrine\Entity\AlertRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\AlertTypeRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\FleetRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\VehicleLastPositionRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\VehicleRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\VehicleTypeRecord;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        if (! $manager instanceof EntityManagerInterface) {
            throw new \RuntimeException('Fixtures require Doctrine ORM entity manager.');
        }

        $now = new \DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $fleetNorth = new FleetRecord();
        $fleetNorth->id = FixtureIds::FLEET_NORTH;
        $fleetNorth->name = 'Northern Operations';
        $fleetNorth->clientName = 'Acme Logistics';
        $fleetNorth->description = 'Cross-border cold-chain fleet';
        $fleetNorth->createdAt = $now;
        $fleetNorth->updatedAt = $now;

        $fleetSouth = new FleetRecord();
        $fleetSouth->id = FixtureIds::FLEET_SOUTH;
        $fleetSouth->name = 'Southern Operations';
        $fleetSouth->clientName = 'Beta Distribution';
        $fleetSouth->description = 'Urban delivery operations';
        $fleetSouth->createdAt = $now;
        $fleetSouth->updatedAt = $now;

        $truck = $this->vehicleType(FixtureIds::VEHICLE_TYPE_TRUCK, 'TRUCK', 'Truck', $now);
        $van = $this->vehicleType(FixtureIds::VEHICLE_TYPE_VAN, 'VAN', 'Van', $now);
        $electricVan = $this->vehicleType(FixtureIds::VEHICLE_TYPE_ELECTRIC_VAN, 'ELECTRIC_VAN', 'Electric Van', $now);

        $speedExceeded = $this->alertType(FixtureIds::ALERT_TYPE_SPEED, 'SPEED_EXCEEDED', 'Speed Exceeded', AlertSeverity::HIGH, $now);
        $geofenceBreach = $this->alertType(FixtureIds::ALERT_TYPE_GEOFENCE, 'GEOFENCE_BREACH', 'Geofence Breach', AlertSeverity::MEDIUM, $now);
        $idleTooLong = $this->alertType(FixtureIds::ALERT_TYPE_IDLE, 'IDLE_TOO_LONG', 'Idle Too Long', AlertSeverity::LOW, $now);

        $vehicleAlpha = $this->vehicle(FixtureIds::VEHICLE_1, 'AAA-111', $truck, $fleetNorth, VehicleStatus::ACTIVE, $now);
        $vehicleBeta = $this->vehicle(FixtureIds::VEHICLE_2, 'BBB-222', $van, $fleetSouth, VehicleStatus::ACTIVE, $now);
        $vehicleGamma = $this->vehicle(FixtureIds::VEHICLE_3, 'CCC-333', $electricVan, null, VehicleStatus::INACTIVE, $now);

        $lastPositionAlpha = new VehicleLastPositionRecord();
        $lastPositionAlpha->vehicle = $vehicleAlpha;
        $lastPositionAlpha->latitude = 48.8566;
        $lastPositionAlpha->longitude = 2.3522;
        $lastPositionAlpha->altitude = 35.0;
        $lastPositionAlpha->speedKmh = 82.5;
        $lastPositionAlpha->accuracy = 4.2;
        $lastPositionAlpha->deviceTimestamp = $now;
        $lastPositionAlpha->receivedAt = $now;
        $lastPositionAlpha->updatedAt = $now;

        $lastPositionBeta = new VehicleLastPositionRecord();
        $lastPositionBeta->vehicle = $vehicleBeta;
        $lastPositionBeta->latitude = 40.4168;
        $lastPositionBeta->longitude = -3.7038;
        $lastPositionBeta->altitude = 28.0;
        $lastPositionBeta->speedKmh = 44.0;
        $lastPositionBeta->accuracy = 3.1;
        $lastPositionBeta->deviceTimestamp = $now;
        $lastPositionBeta->receivedAt = $now;
        $lastPositionBeta->updatedAt = $now;

        $alert = new AlertRecord();
        $alert->id = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        $alert->vehicle = $vehicleAlpha;
        $alert->alertType = $speedExceeded;
        $alert->message = 'Vehicle exceeded speed limit: 140.00 km/h';
        $alert->severity = AlertSeverity::HIGH;
        $alert->createdAt = $now;

        foreach ([$fleetNorth, $fleetSouth, $truck, $van, $electricVan, $speedExceeded, $geofenceBreach, $idleTooLong, $vehicleAlpha, $vehicleBeta, $vehicleGamma, $lastPositionAlpha, $lastPositionBeta, $alert] as $record) {
            $manager->persist($record);
        }

        $manager->flush();

        $manager->getConnection()->insert('gps_coordinates', [
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'external_id' => 'coord-1',
            'vehicle_id' => FixtureIds::VEHICLE_1,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'altitude' => 35.0,
            'speed_kmh' => 82.5,
            'accuracy' => 4.2,
            'device_timestamp' => $now->format('Y-m-d H:i:sP'),
            'received_at' => $now->format('Y-m-d H:i:sP'),
        ]);

        $manager->getConnection()->insert('gps_coordinates', [
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'external_id' => 'coord-2',
            'vehicle_id' => FixtureIds::VEHICLE_1,
            'latitude' => 48.8570,
            'longitude' => 2.3530,
            'altitude' => 36.0,
            'speed_kmh' => 84.0,
            'accuracy' => 4.0,
            'device_timestamp' => $now->modify('+2 seconds')->format('Y-m-d H:i:sP'),
            'received_at' => $now->modify('+2 seconds')->format('Y-m-d H:i:sP'),
        ]);
    }

    private function vehicleType(string $id, string $code, string $name, \DateTimeImmutable $now): VehicleTypeRecord
    {
        $record = new VehicleTypeRecord();
        $record->id = $id;
        $record->code = $code;
        $record->name = $name;
        $record->description = $name . ' vehicle type';
        $record->active = true;
        $record->createdAt = $now;
        $record->updatedAt = $now;

        return $record;
    }

    private function alertType(string $id, string $code, string $name, AlertSeverity $severity, \DateTimeImmutable $now): AlertTypeRecord
    {
        $record = new AlertTypeRecord();
        $record->id = $id;
        $record->code = $code;
        $record->name = $name;
        $record->description = $name . ' alert';
        $record->active = true;
        $record->defaultSeverity = $severity;
        $record->createdAt = $now;
        $record->updatedAt = $now;

        return $record;
    }

    private function vehicle(string $id, string $plate, VehicleTypeRecord $type, ?FleetRecord $fleet, VehicleStatus $status, \DateTimeImmutable $now): VehicleRecord
    {
        $record = new VehicleRecord();
        $record->id = $id;
        $record->plate = $plate;
        $record->vehicleType = $type;
        $record->fleet = $fleet;
        $record->status = $status;
        $record->createdAt = $now;
        $record->updatedAt = $now;

        return $record;
    }
}
