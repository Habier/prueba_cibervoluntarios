<?php

declare(strict_types=1);

namespace App\Api\Output;

use ApiPlatform\Metadata\ApiProperty;

final readonly class LastPositionOutput
{
    public function __construct(
        #[ApiProperty(
            description: 'Latitude coordinate in decimal degrees',
            example: 40.7128,
        )]
        public float $latitude,
        #[ApiProperty(
            description: 'Longitude coordinate in decimal degrees',
            example: -74.0060,
        )]
        public float $longitude,
        #[ApiProperty(
            description: 'Altitude in meters above sea level',
            example: 10.5,
        )]
        public ?float $altitude,
        #[ApiProperty(
            description: 'Speed in kilometers per hour',
            example: 50.0,
        )]
        public float $speedKmh,
        #[ApiProperty(
            description: 'GPS accuracy in meters',
            example: 5.0,
        )]
        public ?float $accuracy,
        #[ApiProperty(
            description: 'Timestamp from the GPS device in ISO 8601 format',
            example: '2024-01-01T12:00:00+00:00',
        )]
        public string $deviceTimestamp,
        #[ApiProperty(
            description: 'Timestamp when the position was received by the system in ISO 8601 format',
            example: '2024-01-01T12:00:05+00:00',
        )]
        public string $receivedAt,
    ) {
    }
}
