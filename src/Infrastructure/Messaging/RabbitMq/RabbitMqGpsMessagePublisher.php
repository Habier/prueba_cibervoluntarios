<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Port\GpsMessagePublisherInterface;
use PhpAmqpLib\Message\AMQPMessage;

final readonly class RabbitMqGpsMessagePublisher implements GpsMessagePublisherInterface
{
    public function __construct(
        private RabbitMqConnectionFactory $connectionFactory,
        private RabbitMqTopologyManager $topologyManager,
        private RabbitMqConfig $config,
    ) {
    }

    public function publish(array $payload): void
    {
        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();
        $this->topologyManager->declare($channel);

        $channel->basic_publish(
            new AMQPMessage((string) json_encode($payload, JSON_THROW_ON_ERROR), [
                'delivery_mode' => 2,
                'content_type' => 'application/json',
            ]),
            $this->config->exchange,
            $this->config->routingKey,
        );

        $channel->close();
        $connection->close();
    }
}
