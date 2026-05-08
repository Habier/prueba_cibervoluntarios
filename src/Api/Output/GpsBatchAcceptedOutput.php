<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiProperty;

final readonly class GpsBatchAcceptedOutput
{
    /**
     * @param list<WarningOutput> $warnings
     */
    public function __construct(
        #[ApiProperty(
            description: 'Number of GPS coordinates accepted for processing',
            example: 100,
        )]
        public int $accepted,
        #[ApiProperty(
            description: 'List of warnings for coordinates that could not be processed',
            example: '[{"type": "validation_error", "message": "Invalid vehicle ID"}]',
        )]
        public array $warnings,
    ) {
    }
}
