<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class Speed
{
    private float $value;

    public function __construct(float $value)
    {
        if ($value < 0) {
            throw new DomainException('Speed must be greater than or equal to zero.');
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

    public function exceeds(float $limit): bool
    {
        return $this->value > $limit;
    }

    public function isStandstill(float $threshold = 0.1): bool
    {
        return $this->value < $threshold;
    }

    public function isIdle(float $idleThreshold = 5.0): bool
    {
        return $this->value < $idleThreshold;
    }
}
