<?php

declare(strict_types=1);

namespace App\Api\Input;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Api\Output\GpsBatchAcceptedOutput;
use App\Infrastructure\ApiPlatform\Processor\GpsCoordinateBatchProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'GpsCoordinateBatch',
    description: 'Endpoint for ingesting GPS coordinate data from vehicles. Accepts batches of GPS coordinates for processing.',
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
    #[ApiProperty(
        description: 'Array of GPS coordinate data points to process',
        example: '[{"vehicleId": "123e4567-e89b-12d3-a456-426614174000", "latitude": 40.7128, "longitude": -74.0060, "speedKmh": 50.0, "deviceTimestamp": "2024-01-01T12:00:00+00:00"}]',
    )]
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Assert\Valid]
    public array $coordinates = [];
}
