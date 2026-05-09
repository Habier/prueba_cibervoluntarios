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
        return $this->speedKmh->exceeds($speedLimitKmh);
    }

    public function isWithinGeofence(float $minLatitude, float $maxLatitude, float $minLongitude, float $maxLongitude): bool
    {
        $lat = $this->latitude->getValue();
        $lon = $this->longitude->getValue();

        return $lat >= $minLatitude && $lat <= $maxLatitude && $lon >= $minLongitude && $lon <= $maxLongitude;
    }

    public function isIdle(float $idleSpeedThreshold = 5.0): bool
    {
        return $this->speedKmh->isIdle($idleSpeedThreshold);
    }
}
