<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Api\Input\GpsCoordinateInput;
use App\Application\Command\IngestGpsCoordinateBatchCommand;
use App\Application\Config\GpsIngestionConfig;
use App\Application\Port\GpsMessagePublisherInterface;
use App\Application\Service\IngestGpsCoordinateBatchHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class IngestGpsCoordinateBatchHandlerTest extends TestCase
{
    public function testPublishesBatchInSinglePublisherCall(): void
    {
        $publisher = new class implements GpsMessagePublisherInterface {
            public int $publishCalls = 0;
            public int $publishBatchCalls = 0;

            /**
             * @var list<array<string, mixed>>
             */
            public array $publishedPayloads = [];

            public function publish(array $payload): void
            {
                ++$this->publishCalls;
                $this->publishedPayloads[] = $payload;
            }

            public function publishBatch(array $payloads): void
            {
                ++$this->publishBatchCalls;
                foreach ($payloads as $payload) {
                    $this->publishedPayloads[] = $payload;
                }
            }
        };

        $handler = new IngestGpsCoordinateBatchHandler(
            $publisher,
            new GpsIngestionConfig(100, 500, 500, 500, 120),
            new NullLogger(),
        );

        $handler->handle(new IngestGpsCoordinateBatchCommand([
            $this->buildCoordinate('ext-1', '2026-01-01T12:00:00+00:00'),
            $this->buildCoordinate('ext-2', '2026-01-01T12:01:00+00:00'),
        ]));

        self::assertSame(0, $publisher->publishCalls);
        self::assertSame(1, $publisher->publishBatchCalls);
        self::assertCount(2, $publisher->publishedPayloads);
    }

    private function buildCoordinate(string $externalId, string $deviceTimestamp): GpsCoordinateInput
    {
        $coordinate = new GpsCoordinateInput();
        $coordinate->externalId = $externalId;
        $coordinate->vehicleId = '88888888-8888-4888-8888-888888888888';
        $coordinate->latitude = 40.0;
        $coordinate->longitude = -3.0;
        $coordinate->speedKmh = 50;
        $coordinate->deviceTimestamp = $deviceTimestamp;

        return $coordinate;
    }
}
