<?php

declare(strict_types=1);

namespace App\Application\Port;

interface DomainEventDispatcherInterface
{
    /**
     * @param list<object> $events
     */
    public function dispatch(array $events): void;
}
