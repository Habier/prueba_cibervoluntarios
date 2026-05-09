<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Gps;

use App\Application\Port\VehicleCatalogInterface;
use App\Infrastructure\Persistence\Doctrine\Entity\VehicleRecord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineVehicleCatalog implements VehicleCatalogInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findKnownVehicleIds(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        /** @var list<string> $knownVehicleIds */
        $knownVehicleIds = $this->entityManager->createQueryBuilder()
            ->select('v.id')
            ->from(VehicleRecord::class, 'v')
            ->where('v.id IN (:vehicleIds)')
            ->setParameter('vehicleIds', array_values(array_unique($vehicleIds)))
            ->getQuery()
            ->getSingleColumnResult();

        return $knownVehicleIds;
    }
}
