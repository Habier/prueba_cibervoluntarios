<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\VehicleTypesProvider;

#[ApiResource(
    operations: [new GetCollection(uriTemplate: '/vehicle-types', provider: VehicleTypesProvider::class)],
)]
final readonly class VehicleTypeOutput
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public bool $active,
    ) {
    }
}
