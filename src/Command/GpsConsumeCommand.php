<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Worker\GpsMessageConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:gps:consume', description: 'Consumes GPS messages from RabbitMQ.')]
final class GpsConsumeCommand extends Command
{
    public function __construct(
        private GpsMessageConsumer $consumer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->consumer->consume();

        return Command::SUCCESS;
    }
}
