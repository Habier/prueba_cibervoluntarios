<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'gps_coordinates')]
class GpsCoordinateRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    public string $id;

    #[ORM\Column]
    public \DateTimeImmutable $deviceTimestamp;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $externalId = null;

    #[ORM\ManyToOne(targetEntity: VehicleRecord::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public VehicleRecord $vehicle;

    #[ORM\Column]
    public float $latitude;

    #[ORM\Column]
    public float $longitude;

    #[ORM\Column(nullable: true)]
    public ?float $altitude = null;

    #[ORM\Column]
    public float $speedKmh;

    #[ORM\Column(nullable: true)]
    public ?float $accuracy = null;

    #[ORM\Column]
    public \DateTimeImmutable $receivedAt;
}
