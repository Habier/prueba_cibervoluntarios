<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Gps;

use App\Application\Port\GpsBatchPersisterInterface;
use App\Domain\Alert\AlertContext;
use App\Domain\Alert\AlertRuleInterface;
use App\Domain\Gps\GpsCoordinate;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineGpsBatchPersister implements GpsBatchPersisterInterface
{
    /**
     * @param iterable<AlertRuleInterface> $alertRules
     */
    public function __construct(
        private Connection $connection,
        private iterable $alertRules,
    ) {
    }

    public function persist(array $coordinates): GpsBatchPersistenceResult
    {
        if ($coordinates === []) {
            return new GpsBatchPersistenceResult([]);
        }

        $vehicleIds = array_values(array_unique(array_map(static fn (GpsCoordinate $coordinate): string => (string) $coordinate->vehicleId, $coordinates)));
        $knownVehicleIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM vehicles WHERE id IN (?)',
            [$vehicleIds],
            [ArrayParameterType::STRING],
        );

        $validCoordinates = array_values(array_filter(
            $coordinates,
            static fn (GpsCoordinate $coordinate): bool => in_array((string) $coordinate->vehicleId, $knownVehicleIds, true),
        ));

        $this->connection->beginTransaction();

        try {
            $insertedCoordinates = $this->insertCoordinates($validCoordinates);
            $this->upsertLastPositions($insertedCoordinates);
            $this->insertAlerts($insertedCoordinates);
            $this->connection->commit();

            return new GpsBatchPersistenceResult($insertedCoordinates);
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * @param list<GpsCoordinate> $coordinates
     *
     * @return list<GpsCoordinate>
     */
    private function insertCoordinates(array $coordinates): array
    {
        if ($coordinates === []) {
            return [];
        }

        $preparedCoordinates = [];
        $dedupValues = [];
        $dedupParameters = [];

        $values = [];
        $parameters = [];

        foreach ($coordinates as $index => $coordinate) {
            $coordinateId = Uuid::v7()->toRfc4122();
            $preparedCoordinates[$coordinateId] = $coordinate;
            $dedupValues[] = sprintf('(:dedupCoordinateId_%1$d, :dedupVehicleId_%1$d, :dedupExternalId_%1$d, :dedupDeviceTimestamp_%1$d, :dedupLatitude_%1$d, :dedupLongitude_%1$d)', $index);
            $dedupParameters[sprintf('dedupCoordinateId_%d', $index)] = $coordinateId;
            $dedupParameters[sprintf('dedupVehicleId_%d', $index)] = (string) $coordinate->vehicleId;
            $dedupParameters[sprintf('dedupExternalId_%d', $index)] = $coordinate->externalId;
            $dedupParameters[sprintf('dedupDeviceTimestamp_%d', $index)] = $coordinate->deviceTimestamp->value->format('Y-m-d H:i:sP');
            $dedupParameters[sprintf('dedupLatitude_%d', $index)] = $coordinate->latitude->value;
            $dedupParameters[sprintf('dedupLongitude_%d', $index)] = $coordinate->longitude->value;
        }

        $acceptedCoordinateIds = $this->connection->fetchFirstColumn(
            sprintf(
                'INSERT INTO gps_coordinate_ingestion_keys (coordinate_id, vehicle_id, external_id, device_timestamp, latitude, longitude) VALUES %s ON CONFLICT DO NOTHING RETURNING coordinate_id',
                implode(', ', $dedupValues),
            ),
            $dedupParameters,
        );

        if ($acceptedCoordinateIds === []) {
            return [];
        }

        foreach ($acceptedCoordinateIds as $index => $acceptedCoordinateId) {
            $coordinate = $preparedCoordinates[(string) $acceptedCoordinateId];
            $values[] = sprintf('(:id_%1$d, :externalId_%1$d, :vehicleId_%1$d, :latitude_%1$d, :longitude_%1$d, :altitude_%1$d, :speedKmh_%1$d, :accuracy_%1$d, :deviceTimestamp_%1$d, :receivedAt_%1$d)', $index);
            $parameters[sprintf('id_%d', $index)] = (string) $acceptedCoordinateId;
            $parameters[sprintf('externalId_%d', $index)] = $coordinate->externalId;
            $parameters[sprintf('vehicleId_%d', $index)] = (string) $coordinate->vehicleId;
            $parameters[sprintf('latitude_%d', $index)] = $coordinate->latitude->value;
            $parameters[sprintf('longitude_%d', $index)] = $coordinate->longitude->value;
            $parameters[sprintf('altitude_%d', $index)] = $coordinate->altitude;
            $parameters[sprintf('speedKmh_%d', $index)] = $coordinate->speedKmh->value;
            $parameters[sprintf('accuracy_%d', $index)] = $coordinate->accuracy;
            $parameters[sprintf('deviceTimestamp_%d', $index)] = $coordinate->deviceTimestamp->value->format('Y-m-d H:i:sP');
            $parameters[sprintf('receivedAt_%d', $index)] = $coordinate->receivedAt->format('Y-m-d H:i:sP');
        }

        $sql = sprintf(
            'INSERT INTO gps_coordinates (id, external_id, vehicle_id, latitude, longitude, altitude, speed_kmh, accuracy, device_timestamp, received_at) VALUES %s RETURNING vehicle_id, latitude, longitude, altitude, speed_kmh, accuracy, device_timestamp, received_at, external_id',
            implode(', ', $values),
        );

        $rows = $this->connection->fetchAllAssociative($sql, $parameters);

        return array_map(
            static fn (array $row): GpsCoordinate => new GpsCoordinate(
                new \App\Domain\Vehicle\ValueObject\VehicleId((string) $row['vehicle_id']),
                new \App\Domain\Gps\ValueObject\Latitude((float) $row['latitude']),
                new \App\Domain\Gps\ValueObject\Longitude((float) $row['longitude']),
                new \App\Domain\Gps\ValueObject\Speed((float) $row['speed_kmh']),
                new \App\Domain\Gps\ValueObject\DeviceTimestamp((string) $row['device_timestamp']),
                new \DateTimeImmutable((string) $row['received_at']),
                $row['external_id'] ? (string) $row['external_id'] : null,
                $row['altitude'] !== null ? (float) $row['altitude'] : null,
                $row['accuracy'] !== null ? (float) $row['accuracy'] : null,
            ),
            $rows,
        );
    }

    /**
     * @param list<GpsCoordinate> $coordinates
     */
    private function upsertLastPositions(array $coordinates): void
    {
        foreach ($coordinates as $index => $coordinate) {
            $this->connection->executeStatement(
                'INSERT INTO vehicle_last_positions (vehicle_id, latitude, longitude, altitude, speed_kmh, accuracy, device_timestamp, received_at, updated_at) VALUES (:vehicleId, :latitude, :longitude, :altitude, :speedKmh, :accuracy, :deviceTimestamp, :receivedAt, :updatedAt)
                ON CONFLICT (vehicle_id) DO UPDATE SET latitude = EXCLUDED.latitude, longitude = EXCLUDED.longitude, altitude = EXCLUDED.altitude, speed_kmh = EXCLUDED.speed_kmh, accuracy = EXCLUDED.accuracy, device_timestamp = EXCLUDED.device_timestamp, received_at = EXCLUDED.received_at, updated_at = EXCLUDED.updated_at
                WHERE EXCLUDED.device_timestamp >= vehicle_last_positions.device_timestamp',
                [
                    'vehicleId' => (string) $coordinate->vehicleId,
                    'latitude' => $coordinate->latitude->value,
                    'longitude' => $coordinate->longitude->value,
                    'altitude' => $coordinate->altitude,
                    'speedKmh' => $coordinate->speedKmh->value,
                    'accuracy' => $coordinate->accuracy,
                    'deviceTimestamp' => $coordinate->deviceTimestamp->value->format('Y-m-d H:i:sP'),
                    'receivedAt' => $coordinate->receivedAt->format('Y-m-d H:i:sP'),
                    'updatedAt' => $coordinate->receivedAt->format('Y-m-d H:i:sP'),
                ],
            );
        }
    }

    /**
     * @param list<GpsCoordinate> $coordinates
     */
    private function insertAlerts(array $coordinates): void
    {
        if ($coordinates === []) {
            return;
        }

        $alertTypeIds = $this->connection->fetchAllKeyValue('SELECT code, id FROM alert_types');

        foreach ($coordinates as $coordinate) {
            foreach ($this->alertRules as $rule) {
                $alert = $rule->evaluate(new AlertContext($coordinate));

                if ($alert === null || ! isset($alertTypeIds[$alert->alertTypeCode])) {
                    continue;
                }

                $this->connection->insert('alerts', [
                    'id' => Uuid::v7()->toRfc4122(),
                    'vehicle_id' => (string) $coordinate->vehicleId,
                    'alert_type_id' => $alertTypeIds[$alert->alertTypeCode],
                    'message' => $alert->message,
                    'severity' => $alert->severity->value,
                    'created_at' => $coordinate->receivedAt->format('Y-m-d H:i:sP'),
                ]);
            }
        }
    }
}
