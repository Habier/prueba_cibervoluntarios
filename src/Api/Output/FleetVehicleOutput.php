<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\FleetVehiclesProvider;

#[ApiResource(
    operations: [new GetCollection(uriTemplate: '/fleets/{id}/vehicles', provider: FleetVehiclesProvider::class)],
)]
final readonly class FleetVehicleOutput
{
    public function __construct(
        public string $id,
        public string $plate,
        public string $status,
    ) {
    }
}
