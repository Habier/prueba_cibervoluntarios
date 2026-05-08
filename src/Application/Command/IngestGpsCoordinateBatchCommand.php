<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Api\Input\GpsCoordinateInput;

final readonly class IngestGpsCoordinateBatchCommand
{
    /**
     * @param list<GpsCoordinateInput> $coordinates
     */
    public function __construct(
        public array $coordinates,
    ) {
    }
}
