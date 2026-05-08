<?php

declare(strict_types=1);

namespace App\Api\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

final class GpsCoordinateInput
{
    #[ApiProperty(
        description: 'External identifier from the GPS device system',
        example: 'GPS-DEVICE-001',
    )]
    #[Assert\Length(max: 255)]
    public ?string $externalId = null;

    #[ApiProperty(
        description: 'Unique identifier of the vehicle (UUID)',
        example: '123e4567-e89b-12d3-a456-426614174000',
    )]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $vehicleId = '';

    #[ApiProperty(
        description: 'Latitude coordinate in decimal degrees (-90 to 90)',
        example: 40.7128,
    )]
    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    public float $latitude;

    #[ApiProperty(
        description: 'Longitude coordinate in decimal degrees (-180 to 180)',
        example: -74.0060,
    )]
    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    public float $longitude;

    #[ApiProperty(
        description: 'Altitude in meters above sea level',
        example: 10.5,
    )]
    public ?float $altitude = null;

    #[ApiProperty(
        description: 'Speed in kilometers per hour',
        example: 50.0,
    )]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    public float $speedKmh;

    #[ApiProperty(
        description: 'GPS accuracy in meters',
        example: 5.0,
    )]
    #[Assert\GreaterThanOrEqual(0)]
    public ?float $accuracy = null;

    #[ApiProperty(
        description: 'Timestamp from the GPS device in ISO 8601 format',
        example: '2024-01-01T12:00:00+00:00',
    )]
    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public string $deviceTimestamp = '';
}
