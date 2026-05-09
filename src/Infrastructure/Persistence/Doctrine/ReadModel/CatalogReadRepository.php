<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\ReadModel;

use App\Api\Output\LastPositionOutput;
use App\Api\Output\VehicleCoordinateOutput;
use App\Api\Output\VehicleOutput;
use App\Infrastructure\Persistence\Doctrine\Entity\GpsCoordinateRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\VehicleRecord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CatalogReadRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<VehicleOutput>
     */
    public function vehicles(): array
    {
        $vehicles = $this->entityManager->createQueryBuilder()
            ->select('v', 'vt', 'vlp')
            ->from(VehicleRecord::class, 'v')
            ->innerJoin('v.vehicleType', 'vt')
            ->leftJoin('v.lastPosition', 'vlp')
            ->orderBy('v.plate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static function (VehicleRecord $vehicle): VehicleOutput {
            $lastPosition = $vehicle->lastPosition;

            return new VehicleOutput(
                $vehicle->id,
                $vehicle->plate,
                $vehicle->status->value,
                [
                    'id' => $vehicle->vehicleType->id,
                    'code' => $vehicle->vehicleType->code,
                    'name' => $vehicle->vehicleType->name,
                ],
                $lastPosition !== null ? new LastPositionOutput(
                    $lastPosition->latitude,
                    $lastPosition->longitude,
                    $lastPosition->altitude,
                    $lastPosition->speedKmh,
                    $lastPosition->accuracy,
                    $lastPosition->deviceTimestamp->format('Y-m-d H:i:sP'),
                    $lastPosition->receivedAt->format('Y-m-d H:i:sP'),
                ) : null,
            );
        }, $vehicles);
    }

    /**
     * @return list<VehicleCoordinateOutput>
     */
    public function vehicleCoordinates(string $vehicleId, VehicleCoordinatesCriteria $criteria): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(GpsCoordinateRecord::class, 'c')
            ->where('c.vehicle = :vehicle')
            ->setParameter('vehicle', $this->entityManager->getReference(VehicleRecord::class, $vehicleId));

        if ($criteria->from !== null) {
            $queryBuilder
                ->andWhere('c.deviceTimestamp >= :from')
                ->setParameter('from', new \DateTimeImmutable($criteria->from));
        }

        if ($criteria->to !== null) {
            $queryBuilder
                ->andWhere('c.deviceTimestamp <= :to')
                ->setParameter('to', new \DateTimeImmutable($criteria->to));
        }

        $coordinates = $queryBuilder
            ->orderBy('c.deviceTimestamp', 'DESC')
            ->setMaxResults($criteria->limit)
            ->getQuery()
            ->getResult();

        return array_map(static fn (GpsCoordinateRecord $coordinate): VehicleCoordinateOutput => new VehicleCoordinateOutput(
            $coordinate->externalId,
            $coordinate->latitude,
            $coordinate->longitude,
            $coordinate->altitude,
            $coordinate->speedKmh,
            $coordinate->accuracy,
            $coordinate->deviceTimestamp->format('Y-m-d H:i:sP'),
            $coordinate->receivedAt->format('Y-m-d H:i:sP'),
        ), $coordinates);
    }
}
