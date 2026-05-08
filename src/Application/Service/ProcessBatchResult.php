<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Infrastructure\Worker\BufferedGpsMessage;

final readonly class ProcessBatchResult
{
    /**
     * @param list<BufferedGpsMessage> $validMessages
     * @param list<BufferedGpsMessage> $invalidMessages
     */
    public function __construct(
        public array $validMessages,
        public array $invalidMessages,
        public int $processedCount,
        public float $insertDurationMs,
    ) {
    }
}
