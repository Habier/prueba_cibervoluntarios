<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

final readonly class DeviceTimestamp
{
    public \DateTimeImmutable $value;

    public function __construct(string|\DateTimeInterface $value)
    {
        if ($value instanceof \DateTimeInterface) {
            $this->value = \DateTimeImmutable::createFromInterface($value);

            return;
        }

        try {
            $this->value = new \DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException('Invalid device timestamp.', previous: $exception);
        }
    }
}
