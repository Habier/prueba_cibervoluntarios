<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Alert\AlertContext;
use App\Domain\Alert\GeofenceBreachRule;
use App\Domain\Alert\IdleTooLongRule;
use App\Domain\Gps\GpsCoordinate;
use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;
use App\Domain\Vehicle\ValueObject\VehicleId;
use PHPUnit\Framework\TestCase;

final class AlertRulesConfigurationTest extends TestCase
{
    public function testIdleRuleUsesConfiguredThreshold(): void
    {
        $rule = new IdleTooLongRule(1.0);

        $alert = $rule->evaluate($this->createContext(latitude: 40.4, longitude: -3.7, speedKmh: 0.9));

        self::assertNotNull($alert);
        self::assertSame('IDLE_TOO_LONG', $alert->alertTypeCode);
    }

    public function testIdleRuleDoesNotTriggerAtOrAboveConfiguredThreshold(): void
    {
        $rule = new IdleTooLongRule(0.5);

        self::assertNull($rule->evaluate($this->createContext(latitude: 40.4, longitude: -3.7, speedKmh: 0.5)));
        self::assertNull($rule->evaluate($this->createContext(latitude: 40.4, longitude: -3.7, speedKmh: 0.7)));
    }

    public function testGeofenceRuleUsesConfiguredBounds(): void
    {
        $rule = new GeofenceBreachRule(40.0, 41.0, -4.0, -3.0);

        $alert = $rule->evaluate($this->createContext(latitude: 41.1, longitude: -3.5, speedKmh: 20.0));

        self::assertNotNull($alert);
        self::assertSame('GEOFENCE_BREACH', $alert->alertTypeCode);
    }

    public function testGeofenceRuleDoesNotTriggerWithinConfiguredBounds(): void
    {
        $rule = new GeofenceBreachRule(40.0, 41.0, -4.0, -3.0);

        self::assertNull($rule->evaluate($this->createContext(latitude: 40.5, longitude: -3.5, speedKmh: 20.0)));
    }

    private function createContext(float $latitude, float $longitude, float $speedKmh): AlertContext
    {
        return new AlertContext(new GpsCoordinate(
            new VehicleId('11111111-1111-4111-8111-111111111111'),
            new Latitude($latitude),
            new Longitude($longitude),
            new Speed($speedKmh),
            new DeviceTimestamp('2026-01-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-01-01T00:00:01+00:00'),
        ));
    }
}
