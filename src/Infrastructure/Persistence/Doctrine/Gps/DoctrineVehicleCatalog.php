<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Gps;

use App\Application\Port\VehicleCatalogInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DoctrineVehicleCatalog implements VehicleCatalogInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findKnownVehicleIds(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT id FROM vehicles WHERE id IN (?)',
            [$vehicleIds],
            [ArrayParameterType::STRING],
        );
    }
}
