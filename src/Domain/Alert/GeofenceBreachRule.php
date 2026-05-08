<?php

declare(strict_types=1);

namespace App\Domain\Alert;

final readonly class GeofenceBreachRule implements AlertRuleInterface
{
    /**
     * Geofence boundaries (example: Madrid city area, roughly).
     * If a coordinate is outside these bounds, it triggers a breach alert.
     */
    private const GEOFENCE_MIN_LATITUDE = 40.3;
    private const GEOFENCE_MAX_LATITUDE = 40.5;
    private const GEOFENCE_MIN_LONGITUDE = -3.8;
    private const GEOFENCE_MAX_LONGITUDE = -3.5;

    public function evaluate(AlertContext $context): ?AlertDraft
    {
        $lat = $context->coordinate->latitude->value;
        $lon = $context->coordinate->longitude->value;

        // Check if coordinate is outside the geofence
        if ($lat < self::GEOFENCE_MIN_LATITUDE 
            || $lat > self::GEOFENCE_MAX_LATITUDE
            || $lon < self::GEOFENCE_MIN_LONGITUDE
            || $lon > self::GEOFENCE_MAX_LONGITUDE) {
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
