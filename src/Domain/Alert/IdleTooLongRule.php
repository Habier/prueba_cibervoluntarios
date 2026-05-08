<?php

declare(strict_types=1);

namespace App\Domain\Alert;

final readonly class IdleTooLongRule implements AlertRuleInterface
{
    /**
     * Speed threshold below which a vehicle is considered idle (in km/h).
     * If speed is below this for an extended period, an alert is triggered.
     * For this test rule: any coordinate with speed < 0.5 km/h is idle.
     */
    private const IDLE_SPEED_THRESHOLD = 0.5;

    public function evaluate(AlertContext $context): ?AlertDraft
    {
        // If vehicle is nearly stationary (speed < threshold), it may be idle
        if ($context->coordinate->speedKmh->value < self::IDLE_SPEED_THRESHOLD) {
            return new AlertDraft(
                $context->coordinate->vehicleId,
                'IDLE_TOO_LONG',
                sprintf('Vehicle is idle: speed %.2f km/h', $context->coordinate->speedKmh->value),
                AlertSeverity::LOW,
            );
        }

        return null;
    }
}
