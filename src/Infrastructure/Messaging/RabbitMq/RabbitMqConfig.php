<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

final readonly class RabbitMqConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        public string $password,
        public string $vhost,
        public string $exchange,
        public string $queue,
        public string $routingKey,
        public string $dlqExchange,
        public string $dlqQueue,
        public string $dlqRoutingKey,
    ) {
    }
}
