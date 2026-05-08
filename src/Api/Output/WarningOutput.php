<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiProperty;

final readonly class WarningOutput
{
    public function __construct(
        #[ApiProperty(
            description: 'Type of warning (e.g., validation_error, processing_error)',
            example: 'validation_error',
        )]
        public string $type,
        #[ApiProperty(
            description: 'Human-readable warning message describing the issue',
            example: 'Invalid vehicle ID format',
        )]
        public string $message,
    ) {
    }
}
