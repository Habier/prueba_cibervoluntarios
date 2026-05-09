<?php

declare(strict_types=1);

namespace App\Domain\Gps\Event;

use App\Domain\Gps\GpsCoordinate;

final readonly class LatestPositionUpdated
{
    public function __construct(
        public GpsCoordinate $observation,
    ) {
    }
}
