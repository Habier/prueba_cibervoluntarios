<?php

declare(strict_types=1);

namespace App\Domain\Common\ValueObject;

use App\Domain\Common\Exception\DomainException;
use Symfony\Component\Uid\Uuid;

abstract readonly class UuidValueObject implements \Stringable
{
    final public function __construct(
        public string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new DomainException(sprintf('Invalid UUID "%s".', $value));
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
