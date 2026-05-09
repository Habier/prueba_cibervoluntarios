<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Gps\GpsCoordinate;

interface LatestPositionPolicyInterface
{
    public function shouldReplace(?LastKnownPosition $current, GpsCoordinate $candidate): bool;
}
