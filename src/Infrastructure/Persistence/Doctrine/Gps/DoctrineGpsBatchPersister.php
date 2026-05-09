<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Gps;

use App\Application\Port\GpsBatchPersisterInterface;
use App\Domain\Alert\AlertContext;
use App\Domain\Alert\AlertRuleInterface;
use App\Domain\Gps\GpsCoordinate;
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

        $this->connection->beginTransaction();

        try {
            $insertedCoordinates = $this->insertCoordinates($coordinates);
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
            $dedupParameters[sprintf('dedupDeviceTimestamp_%d', $index)] = $coordinate->deviceTimestamp->getValue()->format('Y-m-d H:i:sP');
            $dedupParameters[sprintf('dedupLatitude_%d', $index)] = $coordinate->latitude->getValue();
            $dedupParameters[sprintf('dedupLongitude_%d', $index)] = $coordinate->longitude->getValue();
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
            $parameters[sprintf('latitude_%d', $index)] = $coordinate->latitude->getValue();
            $parameters[sprintf('longitude_%d', $index)] = $coordinate->longitude->getValue();
            $parameters[sprintf('altitude_%d', $index)] = $coordinate->altitude;
            $parameters[sprintf('speedKmh_%d', $index)] = $coordinate->speedKmh->getValue();
            $parameters[sprintf('accuracy_%d', $index)] = $coordinate->accuracy;
            $parameters[sprintf('deviceTimestamp_%d', $index)] = $coordinate->deviceTimestamp->getValue()->format('Y-m-d H:i:sP');
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
        if ($coordinates === []) {
            return;
        }

        $values = [];
        $parameters = [];

        foreach ($coordinates as $index => $coordinate) {
            $values[] = sprintf('(:vehicleId_%1$d, :latitude_%1$d, :longitude_%1$d, :altitude_%1$d, :speedKmh_%1$d, :accuracy_%1$d, :deviceTimestamp_%1$d, :receivedAt_%1$d, :updatedAt_%1$d)', $index);
            $parameters[sprintf('vehicleId_%d', $index)] = (string) $coordinate->vehicleId;
            $parameters[sprintf('latitude_%d', $index)] = $coordinate->latitude->getValue();
            $parameters[sprintf('longitude_%d', $index)] = $coordinate->longitude->getValue();
            $parameters[sprintf('altitude_%d', $index)] = $coordinate->altitude;
            $parameters[sprintf('speedKmh_%d', $index)] = $coordinate->speedKmh->getValue();
            $parameters[sprintf('accuracy_%d', $index)] = $coordinate->accuracy;
            $parameters[sprintf('deviceTimestamp_%d', $index)] = $coordinate->deviceTimestamp->getValue()->format('Y-m-d H:i:sP');
            $parameters[sprintf('receivedAt_%d', $index)] = $coordinate->receivedAt->format('Y-m-d H:i:sP');
            $parameters[sprintf('updatedAt_%d', $index)] = $coordinate->receivedAt->format('Y-m-d H:i:sP');
        }

        $this->connection->executeStatement(sprintf(
            'INSERT INTO vehicle_last_positions (vehicle_id, latitude, longitude, altitude, speed_kmh, accuracy, device_timestamp, received_at, updated_at) VALUES %s
            ON CONFLICT (vehicle_id) DO UPDATE SET latitude = EXCLUDED.latitude, longitude = EXCLUDED.longitude, altitude = EXCLUDED.altitude, speed_kmh = EXCLUDED.speed_kmh, accuracy = EXCLUDED.accuracy, device_timestamp = EXCLUDED.device_timestamp, received_at = EXCLUDED.received_at, updated_at = EXCLUDED.updated_at
            WHERE EXCLUDED.device_timestamp >= vehicle_last_positions.device_timestamp',
            implode(', ', $values),
        ), $parameters);
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
        $values = [];
        $parameters = [];
        $alertIndex = 0;

        foreach ($coordinates as $coordinate) {
            foreach ($this->alertRules as $rule) {
                $alert = $rule->evaluate(new AlertContext($coordinate));

                if ($alert === null || ! isset($alertTypeIds[$alert->alertTypeCode])) {
                    continue;
                }

                $values[] = sprintf('(:id_%1$d, :vehicleId_%1$d, :alertTypeId_%1$d, :message_%1$d, :severity_%1$d, :createdAt_%1$d)', $alertIndex);
                $parameters[sprintf('id_%d', $alertIndex)] = Uuid::v7()->toRfc4122();
                $parameters[sprintf('vehicleId_%d', $alertIndex)] = (string) $coordinate->vehicleId;
                $parameters[sprintf('alertTypeId_%d', $alertIndex)] = $alertTypeIds[$alert->alertTypeCode];
                $parameters[sprintf('message_%d', $alertIndex)] = $alert->message;
                $parameters[sprintf('severity_%d', $alertIndex)] = $alert->severity->value;
                $parameters[sprintf('createdAt_%d', $alertIndex)] = $coordinate->receivedAt->format('Y-m-d H:i:sP');
                ++$alertIndex;
            }
        }

        if ($values === []) {
            return;
        }

        $this->connection->executeStatement(
            sprintf('INSERT INTO alerts (id, vehicle_id, alert_type_id, message, severity, created_at) VALUES %s', implode(', ', $values)),
            $parameters,
        );
    }
}
