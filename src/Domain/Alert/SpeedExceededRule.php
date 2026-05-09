<?php

declare(strict_types=1);

namespace App\Domain\Alert;

final readonly class SpeedExceededRule implements AlertRuleInterface
{
    public function __construct(
        private float $speedLimitKmh,
    ) {
    }

    public function evaluate(AlertContext $context): ?AlertDraft
    {
        if (! $context->coordinate->speedKmh->exceeds($this->speedLimitKmh)) {
            return null;
        }

        return new AlertDraft(
            $context->coordinate->vehicleId,
            'SPEED_EXCEEDED',
            sprintf('Vehicle exceeded speed limit: %.2f km/h', $context->coordinate->speedKmh->getValue()),
            AlertSeverity::HIGH,
        );
    }
}
