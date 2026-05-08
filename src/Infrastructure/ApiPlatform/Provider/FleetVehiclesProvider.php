<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Infrastructure\Persistence\Doctrine\ReadModel\CatalogReadRepository;

/** @implements ProviderInterface<\App\Api\Output\FleetVehicleOutput> */
final readonly class FleetVehiclesProvider implements ProviderInterface
{
    public function __construct(
        private CatalogReadRepository $repository,
    ) {
    }

    /**
     * @return list<\App\Api\Output\FleetVehicleOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->repository->fleetVehicles((string) $uriVariables['id']);
    }
}
