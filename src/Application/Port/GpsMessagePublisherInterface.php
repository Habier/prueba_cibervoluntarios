<?php

declare(strict_types=1);

namespace App\Application\Port;

interface GpsMessagePublisherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(array $payload): void;
}
