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
        $lat = $context->coordinate->latitude->getValue();
        $lon = $context->coordinate->longitude->getValue();
        $isWithin = $lat >= $this->minLatitude && $lat <= $this->maxLatitude && $lon >= $this->minLongitude && $lon <= $this->maxLongitude;

        if ($isWithin) {
            return null;
        }

        return new AlertDraft(
            $context->coordinate->vehicleId,
            'GEOFENCE_BREACH',
            sprintf('Vehicle left authorized area: (%.4f, %.4f)', $context->coordinate->latitude->getValue(), $context->coordinate->longitude->getValue()),
            AlertSeverity::MEDIUM,
        );
    }
}
