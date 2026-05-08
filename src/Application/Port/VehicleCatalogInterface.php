<?php

declare(strict_types=1);

namespace App\Application\Port;

interface VehicleCatalogInterface
{
    /**
     * @param list<string> $vehicleIds
     *
     * @return list<string>
     */
    public function findKnownVehicleIds(array $vehicleIds): array;
}
