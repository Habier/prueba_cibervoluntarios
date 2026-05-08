<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Port\DlqPublisherInterface;
use PhpAmqpLib\Message\AMQPMessage;

final readonly class RabbitMqDlqPublisher implements DlqPublisherInterface
{
    public function __construct(
        private RabbitMqConnectionFactory $connectionFactory,
        private RabbitMqTopologyManager $topologyManager,
        private RabbitMqConfig $config,
    ) {
    }

    public function publish(array $payload, string $reason): void
    {
        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();
        $this->topologyManager->declare($channel);

        $payload['dlqReason'] = $reason;

        $channel->basic_publish(
            new AMQPMessage((string) json_encode($payload, JSON_THROW_ON_ERROR), [
                'delivery_mode' => 2,
                'content_type' => 'application/json',
            ]),
            $this->config->dlqExchange,
            $this->config->dlqRoutingKey,
        );

        $channel->close();
        $connection->close();
    }
}
