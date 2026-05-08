<?php

declare(strict_types=1);

namespace App\Api\Output;

final readonly class LastPositionOutput
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?float $altitude,
        public float $speedKmh,
        public ?float $accuracy,
        public string $deviceTimestamp,
        public string $receivedAt,
    ) {
    }
}
