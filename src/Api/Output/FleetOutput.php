<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\FleetsProvider;

#[ApiResource(
    description: 'Fleet management endpoints for managing vehicle fleets',
    operations: [
        new GetCollection(
            uriTemplate: '/fleets',
            provider: FleetsProvider::class,
        ),
    ],
)]
final readonly class FleetOutput
{
    public function __construct(
        #[ApiProperty(
            description: 'Unique identifier of the fleet',
            example: '123e4567-e89b-12d3-a456-426614174000',
        )]
        public string $id,
        #[ApiProperty(
            description: 'Name of the fleet',
            example: 'Emergency Response Fleet',
        )]
        public string $name,
        #[ApiProperty(
            description: 'Name of the client organization that owns the fleet',
            example: 'City Fire Department',
        )]
        public string $clientName,
        #[ApiProperty(
            description: 'Optional description of the fleet',
            example: 'Fleet of emergency response vehicles',
        )]
        public ?string $description,
    ) {
    }
}
