<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\VehiclesProvider;

#[ApiResource(
    description: 'Vehicle management endpoints for tracking and managing vehicles',
    operations: [
        new GetCollection(
            uriTemplate: '/vehicles',
            provider: VehiclesProvider::class,
        ),
    ],
)]
final readonly class VehicleOutput
{
    /**
     * @param array{id:string,code:string,name:string} $vehicleType
     * @param array{id:string,name:string}|null        $fleet
     */
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
        #[ApiProperty(
            description: 'Type classification of the vehicle with code and name',
            example: '{"id": "type-123", "code": "AMB", "name": "Ambulance"}',
        )]
        public array $vehicleType,
        #[ApiProperty(
            description: 'Fleet information if the vehicle belongs to a fleet',
            example: '{"id": "fleet-123", "name": "Emergency Response Fleet"}',
        )]
        public ?array $fleet,
        #[ApiProperty(
            description: 'Last known GPS position of the vehicle',
        )]
        public ?LastPositionOutput $lastPosition,
    ) {
    }
}
