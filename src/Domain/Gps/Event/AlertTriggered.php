<?php

declare(strict_types=1);

namespace App\Domain\Gps\Event;

use App\Domain\Alert\AlertDraft;
use App\Domain\Gps\GpsCoordinate;

final readonly class AlertTriggered
{
    public function __construct(
        public GpsCoordinate $observation,
        public AlertDraft $alert,
    ) {
    }
}
