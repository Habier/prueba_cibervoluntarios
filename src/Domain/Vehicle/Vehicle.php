<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Alert\AlertEvaluationPolicyInterface;
use App\Domain\Gps\Event\AlertTriggered;
use App\Domain\Gps\Event\LatestPositionUpdated;
use App\Domain\Gps\Event\ObservationAccepted;
use App\Domain\Gps\Event\ObservationIgnoredAsDuplicate;
use App\Domain\Gps\GpsCoordinate;
use App\Domain\Vehicle\ValueObject\VehicleId;

final readonly class Vehicle
{
    public function __construct(
        public VehicleId $id,
        public ?LastKnownPosition $lastKnownPosition,
    ) {
    }

    public function recordObservation(
        GpsCoordinate $observation,
        bool $accepted,
        AlertEvaluationPolicyInterface $alertPolicy,
        LatestPositionPolicyInterface $latestPositionPolicy,
    ): VehicleWriteOutcome {
        if (! $accepted) {
            return VehicleWriteOutcome::ignoredAsDuplicate($observation, [new ObservationIgnoredAsDuplicate($observation)]);
        }

        $alerts = $alertPolicy->evaluate($this, $observation);
        $latestPosition = $latestPositionPolicy->shouldReplace($this->lastKnownPosition, $observation)
            ? new LastKnownPosition(
                $observation->latitude,
                $observation->longitude,
                $observation->speedKmh,
                $observation->deviceTimestamp,
                $observation->receivedAt,
                $observation->altitude,
                $observation->accuracy,
            )
            : null;

        $events = [new ObservationAccepted($observation)];
        if ($latestPosition !== null) {
            $events[] = new LatestPositionUpdated($observation);
        }
        foreach ($alerts as $alert) {
            $events[] = new AlertTriggered($observation, $alert);
        }

        return new VehicleWriteOutcome(true, $observation, $latestPosition, $alerts, $events);
    }
}
