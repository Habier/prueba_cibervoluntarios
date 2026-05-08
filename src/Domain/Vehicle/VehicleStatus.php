<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

enum VehicleStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
