<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Alert\AlertDraft;
use App\Domain\Gps\GpsCoordinate;

final readonly class VehicleWriteOutcome
{
    /**
     * @param list<AlertDraft> $alerts
     * @param list<object>     $events
     */
    public function __construct(
        public bool $accepted,
        public GpsCoordinate $observation,
        public ?LastKnownPosition $latestPosition,
        public array $alerts,
        /**
         * @var list<object>
         */
        public array $events,
    ) {
    }

    /**
     * @param list<object> $events
     */
    public static function ignoredAsDuplicate(GpsCoordinate $observation, array $events = []): self
    {
        return new self(false, $observation, null, [], $events);
    }
}
