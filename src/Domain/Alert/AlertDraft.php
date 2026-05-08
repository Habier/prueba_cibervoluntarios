<?php

declare(strict_types=1);

namespace App\Domain\Alert;

use App\Domain\Vehicle\ValueObject\VehicleId;

final readonly class AlertDraft
{
    public function __construct(
        public VehicleId $vehicleId,
        public string $alertTypeCode,
        public string $message,
        public AlertSeverity $severity,
    ) {
    }
}
