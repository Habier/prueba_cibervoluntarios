<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class Longitude
{
    private float $value;

    public function __construct(float $value)
    {
        if ($value < -180 || $value > 180) {
            throw new DomainException('Longitude must be between -180 and 180.');
        }

        $this->value = $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isEasternHemisphere(): bool
    {
        return $this->value >= 0;
    }

    public function isWesternHemisphere(): bool
    {
        return $this->value < 0;
    }
}
