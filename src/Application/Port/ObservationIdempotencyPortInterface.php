<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Gps\GpsCoordinate;

interface ObservationIdempotencyPortInterface
{
    public function claim(GpsCoordinate $observation): bool;
}
