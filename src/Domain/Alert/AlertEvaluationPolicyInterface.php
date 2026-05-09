<?php

declare(strict_types=1);

namespace App\Domain\Alert;

use App\Domain\Gps\GpsCoordinate;
use App\Domain\Vehicle\Vehicle;

interface AlertEvaluationPolicyInterface
{
    /**
     * @return list<AlertDraft>
     */
    public function evaluate(Vehicle $vehicle, GpsCoordinate $observation): array;
}
