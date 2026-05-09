<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Symfony;

use App\Application\Port\DomainEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class SymfonyDomainEventDispatcher implements DomainEventDispatcherInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function dispatch(array $events): void
    {
        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
