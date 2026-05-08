<?php

declare(strict_types=1);

namespace App\Domain\Alert;

final readonly class IdleTooLongRule implements AlertRuleInterface
{
    public function __construct(
        private float $idleSpeedThresholdKmh,
    ) {
    }

    public function evaluate(AlertContext $context): ?AlertDraft
    {
        // If vehicle is nearly stationary (speed < threshold), it may be idle
        if ($context->coordinate->speedKmh->value < $this->idleSpeedThresholdKmh) {
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
