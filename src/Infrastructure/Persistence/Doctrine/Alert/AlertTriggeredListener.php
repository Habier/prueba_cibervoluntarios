<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Alert;

use App\Domain\Gps\Event\AlertTriggered;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: AlertTriggered::class)]
final readonly class AlertTriggeredListener
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(AlertTriggered $event): void
    {
        $alertTypeId = $this->connection->fetchOne(
            'SELECT id FROM alert_types WHERE code = :code',
            ['code' => $event->alert->alertTypeCode],
        );

        if ($alertTypeId === false) {
            return;
        }

        $this->connection->insert('alerts', [
            'id' => Uuid::v7()->toRfc4122(),
            'vehicle_id' => (string) $event->observation->vehicleId,
            'alert_type_id' => $alertTypeId,
            'message' => $event->alert->message,
            'severity' => $event->alert->severity->value,
            'created_at' => $event->observation->receivedAt->format('Y-m-d H:i:sP'),
        ]);
    }
}
