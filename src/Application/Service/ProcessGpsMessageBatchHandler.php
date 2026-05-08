<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\DlqPublisherInterface;
use App\Application\Port\GpsBatchPersisterInterface;
use App\Domain\Gps\GpsCoordinate;
use App\Domain\Gps\ValueObject\DeviceTimestamp;
use App\Domain\Gps\ValueObject\Latitude;
use App\Domain\Gps\ValueObject\Longitude;
use App\Domain\Gps\ValueObject\Speed;
use App\Domain\Vehicle\ValueObject\VehicleId;
use App\Infrastructure\Worker\BufferedGpsMessage;

class ProcessGpsMessageBatchHandler
{
    public function __construct(
        private GpsBatchPersisterInterface $persister,
        private DlqPublisherInterface $dlqPublisher,
    ) {
    }

    /**
     * @param list<BufferedGpsMessage> $messages
     */
    public function handle(array $messages, string $reason): ProcessBatchResult
    {
        $validMessages = [];
        $invalidMessages = [];
        $coordinates = [];

        foreach ($messages as $message) {
            try {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
                $coordinates[] = new GpsCoordinate(
                    new VehicleId((string) $payload['vehicleId']),
                    new Latitude((float) $payload['latitude']),
                    new Longitude((float) $payload['longitude']),
                    new Speed((float) $payload['speedKmh']),
                    new DeviceTimestamp((string) $payload['deviceTimestamp']),
                    new \DateTimeImmutable((string) $payload['receivedAt']),
                    isset($payload['externalId']) ? (string) $payload['externalId'] : null,
                    isset($payload['altitude']) ? (float) $payload['altitude'] : null,
                    isset($payload['accuracy']) ? (float) $payload['accuracy'] : null,
                );
                $validMessages[] = $message;
            } catch (\Throwable $throwable) {
                $this->sendToDlq($message, $throwable);
                $invalidMessages[] = $message;
            }
        }

        $insertDurationMs = 0.0;

        if ($coordinates !== []) {
            $start = microtime(true);
            $this->persister->persist($coordinates);
            $insertDurationMs = (microtime(true) - $start) * 1000;
        }

        return new ProcessBatchResult($validMessages, $invalidMessages, count($coordinates), $insertDurationMs);
    }

    private function sendToDlq(BufferedGpsMessage $message, \Throwable $throwable): void
    {
        try {
            $payload = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $payload = [
                'raw_body' => $message->body,
            ];
        }

        $this->dlqPublisher->publish($payload, $throwable->getMessage());
    }
}
