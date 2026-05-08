<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class Longitude
{
    public function __construct(
        public float $value,
    ) {
        if ($value < -180 || $value > 180) {
            throw new DomainException('Longitude must be between -180 and 180.');
        }
    }
}
