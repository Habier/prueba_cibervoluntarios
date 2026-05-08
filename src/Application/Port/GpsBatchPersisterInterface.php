<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Gps\GpsCoordinate;
use App\Infrastructure\Persistence\Doctrine\Gps\GpsBatchPersistenceResult;

interface GpsBatchPersisterInterface
{
    /**
     * @param list<GpsCoordinate> $coordinates
     */
    public function persist(array $coordinates): GpsBatchPersistenceResult;
}
