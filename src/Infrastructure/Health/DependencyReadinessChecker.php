<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Persistence\Doctrine\Entity\VehicleRecord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DependencyReadinessChecker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RabbitMqConnectionFactory $connectionFactory,
    ) {
    }

    /**
     * @return array{postgresql:bool,rabbitmq:bool}
     */
    public function check(): array
    {
        $postgresql = false;
        $rabbitMq = false;

        try {
            $this->entityManager->createQueryBuilder()
                ->select('v.id')
                ->from(VehicleRecord::class, 'v')
                ->setMaxResults(1)
                ->getQuery()
                ->getScalarResult();
            $postgresql = true;
        } catch (\Throwable) {
        }

        try {
            $connection = $this->connectionFactory->create();
            $channel = $connection->channel();
            $channel->close();
            $connection->close();
            $rabbitMq = true;
        } catch (\Throwable) {
        }

        return [
            'postgresql' => $postgresql,
            'rabbitmq' => $rabbitMq,
        ];
    }
}
