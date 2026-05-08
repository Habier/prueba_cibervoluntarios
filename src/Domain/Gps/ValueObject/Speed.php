<?php

declare(strict_types=1);

namespace App\Domain\Gps\ValueObject;

use App\Domain\Common\Exception\DomainException;

final readonly class Speed
{
    public function __construct(
        public float $value,
    ) {
        if ($value < 0) {
            throw new DomainException('Speed must be greater than or equal to zero.');
        }
    }
}
