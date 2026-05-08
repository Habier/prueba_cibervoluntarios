<?php

declare(strict_types=1);

namespace App\Domain\Gps;

use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;
use App\Domain\Vehicle\ValueObject\VehicleId;

final readonly class GpsCoordinate
{
    public function __construct(
        public VehicleId $vehicleId,
        public Latitude $latitude,
        public Longitude $longitude,
        public Speed $speedKmh,
        public DeviceTimestamp $deviceTimestamp,
        public \DateTimeImmutable $receivedAt,
        public ?string $externalId = null,
        public ?float $altitude = null,
        public ?float $accuracy = null,
    ) {
    }

    public function exceedsSpeedLimit(float $speedLimitKmh): bool
    {
        return $this->speedKmh->value > $speedLimitKmh;
    }
}
