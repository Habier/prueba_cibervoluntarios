<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class Latitude
{
    public function __construct(
        public float $value,
    ) {
        if ($value < -90 || $value > 90) {
            throw new DomainException('Latitude must be between -90 and 90.');
        }
    }
}
