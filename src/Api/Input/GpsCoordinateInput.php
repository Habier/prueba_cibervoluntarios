<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class GpsCoordinateInput
{
    #[Assert\Length(max: 255)]
    public ?string $externalId = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $vehicleId = '';

    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    public float $latitude;

    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    public float $longitude;

    public ?float $altitude = null;

    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    public float $speedKmh;

    #[Assert\GreaterThanOrEqual(0)]
    public ?float $accuracy = null;

    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public string $deviceTimestamp = '';
}
