<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\ReadModel;

use App\Api\Output\FleetOutput;
use App\Api\Output\FleetVehicleOutput;
use App\Api\Output\LastPositionOutput;
use App\Api\Output\VehicleCoordinateOutput;
use App\Api\Output\VehicleOutput;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CatalogReadRepository
{
    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<VehicleOutput>
     */
    public function vehicles(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT v.id, v.plate, v.status, vt.id AS vehicle_type_id, vt.code AS vehicle_type_code, vt.name AS vehicle_type_name, f.id AS fleet_id, f.name AS fleet_name, vlp.latitude, vlp.longitude, vlp.altitude, vlp.speed_kmh, vlp.accuracy, vlp.device_timestamp, vlp.received_at FROM vehicles v INNER JOIN vehicle_types vt ON vt.id = v.vehicle_type_id LEFT JOIN fleets f ON f.id = v.fleet_id LEFT JOIN vehicle_last_positions vlp ON vlp.vehicle_id = v.id ORDER BY v.plate ASC');

        return array_map(static function (array $row): VehicleOutput {
            $lastPosition = null;

            if ($row['latitude'] !== null) {
                $lastPosition = new LastPositionOutput(
                    (float) $row['latitude'],
                    (float) $row['longitude'],
                    $row['altitude'] !== null ? (float) $row['altitude'] : null,
                    (float) $row['speed_kmh'],
                    $row['accuracy'] !== null ? (float) $row['accuracy'] : null,
                    (string) $row['device_timestamp'],
                    (string) $row['received_at'],
                );
            }

            return new VehicleOutput(
                (string) $row['id'],
                (string) $row['plate'],
                (string) $row['status'],
                [
                    'id' => (string) $row['vehicle_type_id'],
                    'code' => (string) $row['vehicle_type_code'],
                    'name' => (string) $row['vehicle_type_name'],
                ],
                $row['fleet_id'] !== null ? [
                    'id' => (string) $row['fleet_id'],
                    'name' => (string) $row['fleet_name'],
                ] : null,
                $lastPosition,
            );
        }, $rows);
    }

    /**
     * @return list<VehicleCoordinateOutput>
     */
    public function vehicleCoordinates(string $vehicleId): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $limit = max(1, min((int) ($request?->query->get('limit', 50) ?? 50), 500));
        $from = $request?->query->get('from');
        $to = $request?->query->get('to');

        $sql = 'SELECT external_id, latitude, longitude, altitude, speed_kmh, accuracy, device_timestamp, received_at FROM gps_coordinates WHERE vehicle_id = :vehicleId';
        $parameters = [
            'vehicleId' => $vehicleId,
        ];

        if (is_string($from) && $from !== '') {
            $sql .= ' AND device_timestamp >= :from';
            $parameters['from'] = $from;
        }

        if (is_string($to) && $to !== '') {
            $sql .= ' AND device_timestamp <= :to';
            $parameters['to'] = $to;
        }

        $sql .= ' ORDER BY device_timestamp DESC LIMIT ' . $limit;

        $rows = $this->connection->fetchAllAssociative($sql, $parameters);

        return array_map(static fn (array $row): VehicleCoordinateOutput => new VehicleCoordinateOutput(
            $row['external_id'] ? (string) $row['external_id'] : null,
            (float) $row['latitude'],
            (float) $row['longitude'],
            $row['altitude'] !== null ? (float) $row['altitude'] : null,
            (float) $row['speed_kmh'],
            $row['accuracy'] !== null ? (float) $row['accuracy'] : null,
            (string) $row['device_timestamp'],
            (string) $row['received_at'],
        ), $rows);
    }

    /**
     * @return list<FleetOutput>
     */
    public function fleets(): array
    {
        return array_map(static fn (array $row): FleetOutput => new FleetOutput((string) $row['id'], (string) $row['name'], (string) $row['client_name'], $row['description'] ? (string) $row['description'] : null), $this->connection->fetchAllAssociative('SELECT id, name, client_name, description FROM fleets ORDER BY name ASC'));
    }

    /**
     * @return list<FleetVehicleOutput>
     */
    public function fleetVehicles(string $fleetId): array
    {
        return array_map(static fn (array $row): FleetVehicleOutput => new FleetVehicleOutput((string) $row['id'], (string) $row['plate'], (string) $row['status']), $this->connection->fetchAllAssociative('SELECT id, plate, status FROM vehicles WHERE fleet_id = :fleetId ORDER BY plate ASC', [
            'fleetId' => $fleetId,
        ]));
    }
}
