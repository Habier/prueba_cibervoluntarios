<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use Doctrine\DBAL\Connection;

final readonly class DependencyReadinessChecker
{
    public function __construct(
        private Connection $connection,
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
            $this->connection->fetchOne('SELECT 1');
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
