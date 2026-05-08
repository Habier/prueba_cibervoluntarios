<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Infrastructure\Persistence\Doctrine\ReadModel\CatalogReadRepository;
use App\Infrastructure\Persistence\Doctrine\ReadModel\VehicleCoordinatesCriteria;

/** @implements ProviderInterface<\App\Api\Output\VehicleCoordinateOutput> */
final readonly class VehicleCoordinatesProvider implements ProviderInterface
{
    public function __construct(
        private CatalogReadRepository $repository,
    ) {
    }

    /**
     * @return list<\App\Api\Output\VehicleCoordinateOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->repository->vehicleCoordinates(
            (string) $uriVariables['id'],
            VehicleCoordinatesCriteria::fromFilters((array) ($context['filters'] ?? [])),
        );
    }
}
