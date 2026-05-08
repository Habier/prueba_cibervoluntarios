<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Output\GpsBatchAcceptedOutput;
use App\Api\Output\WarningOutput;
use App\Application\Command\IngestGpsCoordinateBatchCommand;
use App\Application\Service\IngestGpsCoordinateBatchHandler;

/** @implements ProcessorInterface<mixed, GpsBatchAcceptedOutput> */
final readonly class GpsCoordinateBatchProcessor implements ProcessorInterface
{
    public function __construct(
        private IngestGpsCoordinateBatchHandler $handler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GpsBatchAcceptedOutput
    {
        $result = $this->handler->handle(
            new IngestGpsCoordinateBatchCommand($data->coordinates),
            (bool) ($operation->getExtraProperties()['enforce_max_batch_size'] ?? false),
        );

        return new GpsBatchAcceptedOutput(
            $result['accepted'],
            array_map(
                static fn (array $warning): WarningOutput => new WarningOutput($warning['type'], $warning['message']),
                $result['warnings'],
            ),
        );
    }
}
