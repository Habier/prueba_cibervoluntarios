<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class DeviceTimestamp
{
    private \DateTimeImmutable $value;

    public function __construct(string|\DateTimeInterface $value)
    {
        if ($value instanceof \DateTimeInterface) {
            $this->value = \DateTimeImmutable::createFromInterface($value);

            return;
        }

        try {
            $this->value = new \DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new DomainException('Invalid device timestamp.', previous: $exception);
        }
    }

    public function getValue(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function toDateTimeImmutable(): \DateTimeImmutable
    {
        return $this->value;
    }
}
