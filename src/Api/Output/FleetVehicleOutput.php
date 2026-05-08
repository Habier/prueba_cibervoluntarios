<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\FleetVehiclesProvider;

#[ApiResource(
    description: 'Fleet vehicle endpoints for managing vehicles within a specific fleet',
    operations: [
        new GetCollection(
            uriTemplate: '/fleets/{id}/vehicles',
            provider: FleetVehiclesProvider::class,
        ),
    ],
)]
final readonly class FleetVehicleOutput
{
    public function __construct(
        #[ApiProperty(
            description: 'Unique identifier of the vehicle',
            example: '123e4567-e89b-12d3-a456-426614174000',
        )]
        public string $id,
        #[ApiProperty(
            description: 'License plate number of the vehicle',
            example: 'ABC-1234',
        )]
        public string $plate,
        #[ApiProperty(
            description: 'Current status of the vehicle (e.g., active, inactive, maintenance)',
            example: 'active',
        )]
        public string $status,
    ) {
    }
}
