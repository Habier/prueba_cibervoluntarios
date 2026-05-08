<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:alerts:consume', description: 'Placeholder worker for future alert-specific workflows.')]
final class AlertsConsumeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Alert worker placeholder. Alerts are currently generated inside the GPS transaction.');

        return Command::SUCCESS;
    }
}
