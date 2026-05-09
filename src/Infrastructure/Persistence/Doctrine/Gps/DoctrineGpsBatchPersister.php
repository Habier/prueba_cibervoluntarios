<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Gps;

use App\Application\Port\ObservationIdempotencyPortInterface;
use App\Application\Port\VehicleWriteRepositoryInterface;
use App\Domain\Alert\AlertRuleInterface;
use App\Domain\Alert\RuleBasedAlertEvaluationPolicy;
use App\Domain\Gps\GpsCoordinate;
use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;
use App\Domain\Vehicle\LastKnownPosition;
use App\Domain\Vehicle\LatestPositionByDeviceTimestampPolicy;
use App\Domain\Vehicle\ValueObject\VehicleId;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleWriteOutcome;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineGpsBatchPersister implements ObservationIdempotencyPortInterface, VehicleWriteRepositoryInterface
{
    /**
     * @param iterable<AlertRuleInterface> $alertRules
     */
    public function __construct(
        private Connection $connection,
        private iterable $alertRules,
    ) {
    }

    /**
     * @param list<GpsCoordinate> $coordinates
     */
    public function persist(array $coordinates): GpsBatchPersistenceResult
    {
        $insertedCoordinates = [];

        foreach ($coordinates as $coordinate) {
            if (! $this->claim($coordinate)) {
                continue;
            }

            $vehicle = $this->loadForUpdate($coordinate->vehicleId);
            $outcome = $vehicle->recordObservation(
                $coordinate,
                true,
                new RuleBasedAlertEvaluationPolicy($this->alertRules),
                new LatestPositionByDeviceTimestampPolicy(),
            );
            $this->save($outcome);
            $insertedCoordinates[] = $coordinate;
        }

        return new GpsBatchPersistenceResult($insertedCoordinates);
    }

    public function claim(GpsCoordinate $observation): bool
    {
        $inserted = $this->connection->fetchOne(
            'INSERT INTO gps_coordinate_ingestion_keys (coordinate_id, vehicle_id, external_id, device_timestamp, latitude, longitude)
             VALUES (:coordinateId, :vehicleId, :externalId, :deviceTimestamp, :latitude, :longitude)
             ON CONFLICT DO NOTHING RETURNING coordinate_id',
            [
                'coordinateId' => Uuid::v7()->toRfc4122(),
                'vehicleId' => (string) $observation->vehicleId,
                'externalId' => $observation->externalId,
                'deviceTimestamp' => $observation->deviceTimestamp->getValue()->format('Y-m-d H:i:sP'),
                'latitude' => $observation->latitude->getValue(),
                'longitude' => $observation->longitude->getValue(),
            ],
        );

        return $inserted !== false;
    }

    public function loadForUpdate(VehicleId $vehicleId): Vehicle
    {
        $row = $this->connection->fetchAssociative(
            'SELECT latitude, longitude, altitude, speed_kmh, accuracy, device_timestamp, received_at
             FROM vehicle_last_positions
             WHERE vehicle_id = :vehicleId',
            [
                'vehicleId' => (string) $vehicleId,
            ],
        );

        $position = null;
        if ($row !== false) {
            $position = new LastKnownPosition(
                new Latitude((float) $row['latitude']),
                new Longitude((float) $row['longitude']),
                new Speed((float) $row['speed_kmh']),
                new DeviceTimestamp((string) $row['device_timestamp']),
                new \DateTimeImmutable((string) $row['received_at']),
                $row['altitude'] !== null ? (float) $row['altitude'] : null,
                $row['accuracy'] !== null ? (float) $row['accuracy'] : null,
            );
        }

        return new Vehicle($vehicleId, $position);
    }

    public function save(VehicleWriteOutcome $outcome): void
    {
        if (! $outcome->accepted) {
            return;
        }

        $coordinate = $outcome->observation;
        $this->connection->beginTransaction();

        try {
            $this->connection->insert('gps_coordinates', [
                'id' => Uuid::v7()->toRfc4122(),
                'external_id' => $coordinate->externalId,
                'vehicle_id' => (string) $coordinate->vehicleId,
                'latitude' => $coordinate->latitude->getValue(),
                'longitude' => $coordinate->longitude->getValue(),
                'altitude' => $coordinate->altitude,
                'speed_kmh' => $coordinate->speedKmh->getValue(),
                'accuracy' => $coordinate->accuracy,
                'device_timestamp' => $coordinate->deviceTimestamp->getValue()->format('Y-m-d H:i:sP'),
                'received_at' => $coordinate->receivedAt->format('Y-m-d H:i:sP'),
            ]);

            if ($outcome->latestPosition !== null) {
                $position = $outcome->latestPosition;
                $this->connection->executeStatement(
                    'INSERT INTO vehicle_last_positions (vehicle_id, latitude, longitude, altitude, speed_kmh, accuracy, device_timestamp, received_at, updated_at)
                     VALUES (:vehicleId, :latitude, :longitude, :altitude, :speedKmh, :accuracy, :deviceTimestamp, :receivedAt, :updatedAt)
                     ON CONFLICT (vehicle_id) DO UPDATE SET latitude = EXCLUDED.latitude, longitude = EXCLUDED.longitude, altitude = EXCLUDED.altitude, speed_kmh = EXCLUDED.speed_kmh, accuracy = EXCLUDED.accuracy, device_timestamp = EXCLUDED.device_timestamp, received_at = EXCLUDED.received_at, updated_at = EXCLUDED.updated_at
                     WHERE EXCLUDED.device_timestamp >= vehicle_last_positions.device_timestamp',
                    [
                        'vehicleId' => (string) $coordinate->vehicleId,
                        'latitude' => $position->latitude->getValue(),
                        'longitude' => $position->longitude->getValue(),
                        'altitude' => $position->altitude,
                        'speedKmh' => $position->speedKmh->getValue(),
                        'accuracy' => $position->accuracy,
                        'deviceTimestamp' => $position->deviceTimestamp->getValue()->format('Y-m-d H:i:sP'),
                        'receivedAt' => $position->receivedAt->format('Y-m-d H:i:sP'),
                        'updatedAt' => $position->receivedAt->format('Y-m-d H:i:sP'),
                    ],
                );
            }

            $this->connection->commit();
        } catch (\Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }
}
