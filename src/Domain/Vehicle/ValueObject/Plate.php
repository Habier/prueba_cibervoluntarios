<?php

declare(strict_types=1);

namespace App\Domain\Vehicle\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class Plate
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new DomainException('Plate cannot be empty.');
        }

        $this->value = strtoupper($normalized);
    }
}
