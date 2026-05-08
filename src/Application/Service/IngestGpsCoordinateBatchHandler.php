<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\IngestGpsCoordinateBatchCommand;
use App\Application\Config\GpsIngestionConfig;
use App\Application\Port\GpsMessagePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class IngestGpsCoordinateBatchHandler
{
    public function __construct(
        private GpsMessagePublisherInterface $publisher,
        private GpsIngestionConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{accepted:int,warnings:list<array{type:string,message:string}>}
     */
    public function handle(IngestGpsCoordinateBatchCommand $command, bool $enforceMaxBatchSize = false): array
    {
        if ($enforceMaxBatchSize && count($command->coordinates) > $this->config->maxBatchRequestSize) {
            throw new BadRequestHttpException(sprintf('Batch size cannot exceed %d records.', $this->config->maxBatchRequestSize));
        }

        $futureTimestamps = 0;
        $now = new \DateTimeImmutable();

        foreach ($command->coordinates as $coordinate) {
            if (new \DateTimeImmutable($coordinate->deviceTimestamp) > $now) {
                ++$futureTimestamps;
            }

            $this->publisher->publish([
                'externalId' => $coordinate->externalId,
                'vehicleId' => $coordinate->vehicleId,
                'latitude' => $coordinate->latitude,
                'longitude' => $coordinate->longitude,
                'altitude' => $coordinate->altitude,
                'speedKmh' => $coordinate->speedKmh,
                'accuracy' => $coordinate->accuracy,
                'deviceTimestamp' => $coordinate->deviceTimestamp,
                'receivedAt' => $now->format(DATE_ATOM),
            ]);
        }

        $warnings = [];

        if ($futureTimestamps > 0) {
            $warnings[] = [
                'type' => 'FUTURE_DEVICE_TIMESTAMP',
                'message' => sprintf('%d coordinates have a device timestamp in the future', $futureTimestamps),
            ];

            $this->logger->warning('Future device timestamp accepted.', [
                'warning_type' => 'FUTURE_DEVICE_TIMESTAMP',
                'future_coordinates_count' => $futureTimestamps,
            ]);
        }

        return [
            'accepted' => count($command->coordinates),
            'warnings' => $warnings,
        ];
    }
}
