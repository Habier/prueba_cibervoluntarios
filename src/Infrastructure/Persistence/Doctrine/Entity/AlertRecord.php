<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Alert\AlertSeverity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'alerts')]
class AlertRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    public string $id;

    #[ORM\ManyToOne(targetEntity: VehicleRecord::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public VehicleRecord $vehicle;

    #[ORM\ManyToOne(targetEntity: AlertTypeRecord::class)]
    #[ORM\JoinColumn(name: 'alert_type_id', referencedColumnName: 'id', nullable: false)]
    public AlertTypeRecord $alertType;

    #[ORM\Column(type: 'text')]
    public string $message;

    #[ORM\Column(enumType: AlertSeverity::class)]
    public AlertSeverity $severity;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
}
