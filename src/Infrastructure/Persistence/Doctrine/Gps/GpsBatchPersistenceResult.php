<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Gps;

use App\Domain\Gps\GpsCoordinate;

final readonly class GpsBatchPersistenceResult
{
    /**
     * @param list<GpsCoordinate> $insertedCoordinates
     */
    public function __construct(
        public array $insertedCoordinates,
    ) {
    }
}
