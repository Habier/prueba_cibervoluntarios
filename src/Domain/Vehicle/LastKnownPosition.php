<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;

final readonly class LastKnownPosition
{
    public function __construct(
        public Latitude $latitude,
        public Longitude $longitude,
        public Speed $speedKmh,
        public DeviceTimestamp $deviceTimestamp,
        public \DateTimeImmutable $receivedAt,
        public ?float $altitude = null,
        public ?float $accuracy = null,
    ) {
    }
}
