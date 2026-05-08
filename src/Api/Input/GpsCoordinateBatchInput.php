<?php

declare(strict_types=1);

namespace App\Api\Input;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Api\Output\GpsBatchAcceptedOutput;
use App\Infrastructure\ApiPlatform\Processor\GpsCoordinateBatchProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'GpsCoordinateBatch',
    operations: [
        new Post(
            uriTemplate: '/gps-coordinates',
            status: 202,
            processor: GpsCoordinateBatchProcessor::class,
            output: GpsBatchAcceptedOutput::class,
        ),
        new Post(
            uriTemplate: '/gps-coordinates/batch',
            status: 202,
            processor: GpsCoordinateBatchProcessor::class,
            output: GpsBatchAcceptedOutput::class,
            extraProperties: [
                'enforce_max_batch_size' => true,
            ],
        ),
    ],
)]
final class GpsCoordinateBatchInput
{
    /**
     * @var list<GpsCoordinateInput>
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Assert\Valid]
    public array $coordinates = [];
}
