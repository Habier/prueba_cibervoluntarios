<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Vehicle\VehicleStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicles')]
class VehicleRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    public string $id;

    #[ORM\Column(length: 32, unique: true)]
    public string $plate;

    #[ORM\ManyToOne(targetEntity: VehicleTypeRecord::class)]
    #[ORM\JoinColumn(name: 'vehicle_type_id', referencedColumnName: 'id', nullable: false)]
    public VehicleTypeRecord $vehicleType;

    #[ORM\Column(enumType: VehicleStatus::class)]
    public VehicleStatus $status;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;
}
