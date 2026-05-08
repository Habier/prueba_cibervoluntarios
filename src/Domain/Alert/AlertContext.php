<?php

declare(strict_types=1);

namespace App\Domain\Alert;

use App\Domain\Gps\GpsCoordinate;

final readonly class AlertContext
{
    public function __construct(
        public GpsCoordinate $coordinate,
    ) {
    }
}
