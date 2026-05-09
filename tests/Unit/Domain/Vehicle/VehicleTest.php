<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Vehicle;

use App\Domain\Alert\AlertEvaluationPolicyInterface;
use App\Domain\Gps\GpsCoordinate;
use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;
use App\Domain\Vehicle\LastKnownPosition;
use App\Domain\Vehicle\LatestPositionByDeviceTimestampPolicy;
use App\Domain\Vehicle\ValueObject\VehicleId;
use App\Domain\Vehicle\Vehicle;
use PHPUnit\Framework\TestCase;

final class VehicleTest extends TestCase
{
    public function testDuplicateObservationProducesNoWriteEffects(): void
    {
        $vehicle = new Vehicle(new VehicleId('00000000-0000-4000-8000-000000000001'), null);
        $observation = $this->observation('2026-01-01T00:00:00+00:00');

        $outcome = $vehicle->recordObservation($observation, false, $this->emptyAlertPolicy(), new LatestPositionByDeviceTimestampPolicy());

        self::assertFalse($outcome->accepted);
        self::assertNull($outcome->latestPosition);
        self::assertSame([], $outcome->alerts);
    }

    public function testEqualTimestampReplacesLatestPosition(): void
    {
        $vehicleId = new VehicleId('00000000-0000-4000-8000-000000000001');
        $last = new LastKnownPosition(
            new Latitude(40.0),
            new Longitude(-3.0),
            new Speed(10.0),
            new DeviceTimestamp('2026-01-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
        $vehicle = new Vehicle($vehicleId, $last);
        $observation = $this->observation('2026-01-01T00:00:00+00:00');

        $outcome = $vehicle->recordObservation($observation, true, $this->emptyAlertPolicy(), new LatestPositionByDeviceTimestampPolicy());

        self::assertTrue($outcome->accepted);
        self::assertNotNull($outcome->latestPosition);
        self::assertSame('2026-01-01 00:00:00+00:00', $outcome->latestPosition->deviceTimestamp->getValue()->format('Y-m-d H:i:sP'));
    }

    private function observation(string $deviceTimestamp): GpsCoordinate
    {
        return new GpsCoordinate(
            new VehicleId('00000000-0000-4000-8000-000000000001'),
            new Latitude(40.1),
            new Longitude(-3.1),
            new Speed(20.0),
            new DeviceTimestamp($deviceTimestamp),
            new \DateTimeImmutable('2026-01-01T00:00:01+00:00'),
        );
    }

    private function emptyAlertPolicy(): AlertEvaluationPolicyInterface
    {
        return new class implements AlertEvaluationPolicyInterface {
            public function evaluate(Vehicle $vehicle, GpsCoordinate $observation): array
            {
                return [];
            }
        };
    }
}
