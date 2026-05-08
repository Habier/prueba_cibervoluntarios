<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Config\GpsIngestionConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use App\Infrastructure\Worker\BufferedGpsMessage;
use App\Infrastructure\Worker\GpsMessageBuffer;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:gps:consume', description: 'Consumes GPS messages from RabbitMQ.')]
final class GpsConsumeCommand extends Command
{
    public function __construct(
        private RabbitMqConnectionFactory $connectionFactory,
        private RabbitMqTopologyManager $topologyManager,
        private RabbitMqConfig $config,
        private GpsIngestionConfig $ingestionConfig,
        private GpsMessageBuffer $buffer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
                        $deliveryInfo['channel']->basic_ack((string) $deliveryInfo['delivery_tag']);
                    },
                    static function (bool $requeue) use ($message): void {
                        /** @var array{channel:\PhpAmqpLib\Channel\AMQPChannel,delivery_tag:int} $deliveryInfo */
                        $deliveryInfo = $message->delivery_info;
                        $deliveryInfo['channel']->basic_reject((string) $deliveryInfo['delivery_tag'], $requeue);
                    },
                ));
            },
        );

        while ($channel->callbacks !== []) {
            try {
                $channel->wait(null, false, max(1, (int) ceil($this->ingestionConfig->flushTimeoutMs / 1000)));
            } catch (AMQPTimeoutException) {
                $this->buffer->flushIfTimedOut();
            }
        }

        $channel->close();
        $connection->close();

        return Command::SUCCESS;
    }
}
