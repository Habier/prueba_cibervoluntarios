<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;

final class RabbitMqTopologyManager
{
    private bool $declaredInProcess = false;

    public function __construct(
        private RabbitMqConfig $config,
    ) {
    }

    public function declare(AMQPChannel $channel): void
    {
        if ($this->declaredInProcess) {
            return;
        }

        $channel->exchange_declare($this->config->exchange, 'direct', false, true, false);
        $channel->exchange_declare($this->config->dlqExchange, 'direct', false, true, false);

        $channel->queue_declare(
            $this->config->queue,
            false,
            true,
            false,
            false,
            false,
            [
                'x-dead-letter-exchange' => ['S', $this->config->dlqExchange],
                'x-dead-letter-routing-key' => ['S', $this->config->dlqRoutingKey],
            ],
        );
        $channel->queue_declare($this->config->dlqQueue, false, true, false, false);

        $channel->queue_bind($this->config->queue, $this->config->exchange, $this->config->routingKey);
        $channel->queue_bind($this->config->dlqQueue, $this->config->dlqExchange, $this->config->dlqRoutingKey);

        // RabbitMQ topology is durable and global to the vhost. Re-declaring on
        // every publish call adds avoidable round-trips, so we do it once per PHP process.
        $this->declaredInProcess = true;
    }
}
