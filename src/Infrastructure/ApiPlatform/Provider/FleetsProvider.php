<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Infrastructure\Persistence\Doctrine\ReadModel\CatalogReadRepository;

/** @implements ProviderInterface<\App\Api\Output\FleetOutput> */
final readonly class FleetsProvider implements ProviderInterface
{
    public function __construct(
        private CatalogReadRepository $repository,
    ) {
    }

    /**
     * @return list<\App\Api\Output\FleetOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->repository->fleets();
    }
}
