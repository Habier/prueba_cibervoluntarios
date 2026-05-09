<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class Latitude
{
    private float $value;

    public function __construct(float $value)
    {
        if ($value < -90 || $value > 90) {
            throw new DomainException('Latitude must be between -90 and 90.');
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

    public function isNorthernHemisphere(): bool
    {
        return $this->value >= 0;
    }

    public function isSouthernHemisphere(): bool
    {
        return $this->value < 0;
    }
}
