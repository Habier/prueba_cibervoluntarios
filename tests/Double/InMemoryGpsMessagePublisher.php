<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\Port\GpsMessagePublisherInterface;

final class InMemoryGpsMessagePublisher implements GpsMessagePublisherInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $messages = [];

    public function publish(array $payload): void
    {
        $this->messages[] = $payload;
    }

    public function reset(): void
    {
        $this->messages = [];
    }
}
