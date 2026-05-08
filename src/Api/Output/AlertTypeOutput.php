<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\AlertTypesProvider;

#[ApiResource(
    operations: [new GetCollection(uriTemplate: '/alert-types', provider: AlertTypesProvider::class)],
)]
final readonly class AlertTypeOutput
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public bool $active,
        public string $defaultSeverity,
    ) {
    }
}
