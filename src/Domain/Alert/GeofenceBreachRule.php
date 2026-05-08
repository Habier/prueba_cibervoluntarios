<?php

declare(strict_types=1);

namespace App\Domain\Alert;

final readonly class GeofenceBreachRule implements AlertRuleInterface
{
    public function __construct(
        private float $minLatitude,
        private float $maxLatitude,
        private float $minLongitude,
        private float $maxLongitude,
    ) {
    }

    public function evaluate(AlertContext $context): ?AlertDraft
    {
        $lat = $context->coordinate->latitude->value;
        $lon = $context->coordinate->longitude->value;

        // Check if coordinate is outside the geofence
        if ($lat < $this->minLatitude
            || $lat > $this->maxLatitude
            || $lon < $this->minLongitude
            || $lon > $this->maxLongitude) {
            return new AlertDraft(
                $context->coordinate->vehicleId,
                'GEOFENCE_BREACH',
                sprintf('Vehicle left authorized area: (%.4f, %.4f)', $lat, $lon),
                AlertSeverity::MEDIUM,
            );
        }

        return null;
    }
}
