<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

use App\Application\Config\GpsIngestionConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

final readonly class GpsMessageConsumer
{
    public function __construct(
        private RabbitMqConnectionFactory $connectionFactory,
        private RabbitMqTopologyManager $topologyManager,
        private RabbitMqConfig $config,
        private GpsIngestionConfig $ingestionConfig,
        private GpsMessageBuffer $buffer,
    ) {
    }

    public function consume(?int $maxIdleTimeouts = null): void
    {
        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();
        $this->topologyManager->declare($channel);
        $channel->basic_qos(0, $this->ingestionConfig->prefetchCount, false);

        $channel->basic_consume(
            $this->config->queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $message): void {
                $this->buffer->add(new BufferedGpsMessage(
                    $message->getBody(),
                    static function () use ($message): void {
                        /** @var array{channel:\PhpAmqpLib\Channel\AMQPChannel,delivery_tag:int} $deliveryInfo */
                        $deliveryInfo = $message->delivery_info;
                        $deliveryInfo['channel']->basic_ack($deliveryInfo['delivery_tag']);
                    },
                    static function (bool $requeue) use ($message): void {
                        /** @var array{channel:\PhpAmqpLib\Channel\AMQPChannel,delivery_tag:int} $deliveryInfo */
                        $deliveryInfo = $message->delivery_info;
                        $deliveryInfo['channel']->basic_reject($deliveryInfo['delivery_tag'], $requeue);
                    },
                ));
            },
        );

        $idleTimeouts = 0;

        try {
            while ($channel->callbacks !== []) {
                try {
                    $channel->wait(null, false, max(1, (int) ceil($this->ingestionConfig->flushTimeoutMs / 1000)));
                    $idleTimeouts = 0;
                } catch (AMQPTimeoutException) {
                    $this->buffer->flushIfTimedOut();
                    ++$idleTimeouts;

                    if ($maxIdleTimeouts !== null && $idleTimeouts >= $maxIdleTimeouts) {
                        break;
                    }
                }
            }
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
