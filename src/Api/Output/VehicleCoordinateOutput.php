<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\VehicleCoordinatesProvider;

#[ApiResource(
    operations: [new GetCollection(uriTemplate: '/vehicles/{id}/coordinates', provider: VehicleCoordinatesProvider::class)],
)]
final readonly class VehicleCoordinateOutput
{
    public function __construct(
        public ?string $externalId,
        public float $latitude,
        public float $longitude,
        public ?float $altitude,
        public float $speedKmh,
        public ?float $accuracy,
        public string $deviceTimestamp,
        public string $receivedAt,
    ) {
    }
}
