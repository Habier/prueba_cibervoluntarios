<?php

declare(strict_types=1);

namespace App\Application\Exception;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class UnknownVehicleIdsException extends BadRequestHttpException implements ProblemExceptionInterface
{
    /**
     * @param list<string> $unknownVehicleIds
     */
    public function __construct(
        private readonly array $unknownVehicleIds,
    ) {
        parent::__construct(self::buildDetail($unknownVehicleIds));
    }

    public function getType(): string
    {
        return 'https://api-platform.com/errors/unknown-vehicle-id';
    }

    public function getTitle(): ?string
    {
        return 'Unknown vehicle identifier';
    }

    public function getStatus(): ?int
    {
        return 400;
    }

    public function getDetail(): ?string
    {
        return $this->getMessage();
    }

    public function getInstance(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function unknownVehicleIds(): array
    {
        return $this->unknownVehicleIds;
    }

    /**
     * @param list<string> $unknownVehicleIds
     */
    private static function buildDetail(array $unknownVehicleIds): string
    {
        return sprintf('Unknown vehicleId(s): %s', implode(', ', $unknownVehicleIds));
    }
}
