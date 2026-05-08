<?php

declare(strict_types=1);

namespace App\Api\Output;

final readonly class GpsBatchAcceptedOutput
{
    /**
     * @param list<WarningOutput> $warnings
     */
    public function __construct(
        public int $accepted,
        public array $warnings,
    ) {
    }
}
