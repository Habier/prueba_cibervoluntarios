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
        if (! $context->coordinate->isIdle($this->idleSpeedThresholdKmh)) {
            return null;
        }

        return new AlertDraft(
            $context->coordinate->vehicleId,
            'IDLE_TOO_LONG',
            sprintf('Vehicle is idle: speed %.2f km/h', $context->coordinate->speedKmh->getValue()),
            AlertSeverity::LOW,
        );
    }
}
