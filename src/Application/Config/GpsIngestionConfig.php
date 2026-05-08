<?php

declare(strict_types=1);

namespace App\Application\Config;

final readonly class GpsIngestionConfig
{
    public function __construct(
        public int $batchSize,
        public int $flushTimeoutMs,
        public int $prefetchCount,
        public int $maxBatchRequestSize,
        public float $speedLimitKmh,
    ) {
    }
}
