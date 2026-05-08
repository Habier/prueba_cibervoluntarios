<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\FleetsProvider;

#[ApiResource(
    operations: [new GetCollection(uriTemplate: '/fleets', provider: FleetsProvider::class)],
)]
final readonly class FleetOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $clientName,
        public ?string $description,
    ) {
    }
}
