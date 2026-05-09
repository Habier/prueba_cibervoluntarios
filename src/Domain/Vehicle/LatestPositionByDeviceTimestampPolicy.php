<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Gps\GpsCoordinate;

final class LatestPositionByDeviceTimestampPolicy implements LatestPositionPolicyInterface
{
    public function shouldReplace(?LastKnownPosition $current, GpsCoordinate $candidate): bool
    {
        if ($current === null) {
            return true;
        }

        return $candidate->deviceTimestamp->getValue() >= $current->deviceTimestamp->getValue();
    }
}
