<?php

declare(strict_types=1);

namespace App\DataFixtures;

final class FixtureIds
{
    public const string FLEET_NORTH = '11111111-1111-4111-8111-111111111111';
    public const string FLEET_SOUTH = '22222222-2222-4222-8222-222222222222';

    public const string VEHICLE_TYPE_TRUCK = '33333333-3333-4333-8333-333333333333';
    public const string VEHICLE_TYPE_VAN = '44444444-4444-4444-8444-444444444444';
    public const string VEHICLE_TYPE_ELECTRIC_VAN = '55555555-5555-4555-8555-555555555555';

    public const string ALERT_TYPE_SPEED = '66666666-6666-4666-8666-666666666666';
    public const string ALERT_TYPE_GEOFENCE = '77777777-7777-4777-8777-777777777777';

    public const string VEHICLE_1 = '88888888-8888-4888-8888-888888888888';
    public const string VEHICLE_2 = '99999999-9999-4999-8999-999999999999';
    public const string VEHICLE_3 = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    private function __construct()
    {
    }
}
