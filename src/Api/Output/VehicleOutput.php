<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\VehiclesProvider;

#[ApiResource(
    operations: [new GetCollection(uriTemplate: '/vehicles', provider: VehiclesProvider::class)],
)]
final readonly class VehicleOutput
{
    /**
     * @param array{id:string,code:string,name:string} $vehicleType
     * @param array{id:string,name:string}|null        $fleet
     */
    public function __construct(
        public string $id,
        public string $plate,
        public string $status,
        public array $vehicleType,
        public ?array $fleet,
        public ?LastPositionOutput $lastPosition,
    ) {
    }
}
