<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Vehicle\ValueObject\VehicleId;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleWriteOutcome;

interface VehicleWriteRepositoryInterface
{
    public function loadForUpdate(VehicleId $vehicleId): Vehicle;

    public function save(VehicleWriteOutcome $outcome): void;
}
