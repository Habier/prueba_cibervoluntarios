<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\ReadModel;

final readonly class VehicleCoordinatesCriteria
{
    public function __construct(
        public int $limit = 50,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function fromFilters(array $filters): self
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 50), 500));
        $from = is_string($filters['from'] ?? null) && $filters['from'] !== '' ? $filters['from'] : null;
        $to = is_string($filters['to'] ?? null) && $filters['to'] !== '' ? $filters['to'] : null;

        return new self($limit, $from, $to);
    }
}
