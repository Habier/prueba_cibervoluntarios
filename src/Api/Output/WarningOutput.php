<?php

declare(strict_types=1);

namespace App\Api\Output;

final readonly class WarningOutput
{
    public function __construct(
        public string $type,
        public string $message,
    ) {
    }
}
