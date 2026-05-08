<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Api\Input\GpsCoordinateInput;
use App\Application\Command\IngestGpsCoordinateBatchCommand;
use App\Application\Config\GpsIngestionConfig;
use App\Application\Exception\UnknownVehicleIdsException;
use App\Application\Port\GpsMessagePublisherInterface;
use App\Application\Port\VehicleCatalogInterface;
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
            new class implements VehicleCatalogInterface {
                public function findKnownVehicleIds(array $vehicleIds): array
                {
                    return $vehicleIds;
                }
            },
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

    public function testMixedKnownAndUnknownVehicleIdsRejectsWholeBatchWithoutPublishing(): void
    {
        $publisher = new class implements GpsMessagePublisherInterface {
            public int $publishBatchCalls = 0;

            public function publish(array $payload): void
            {
            }

            public function publishBatch(array $payloads): void
            {
                ++$this->publishBatchCalls;
            }
        };

        $handler = new IngestGpsCoordinateBatchHandler(
            $publisher,
            new class implements VehicleCatalogInterface {
                public function findKnownVehicleIds(array $vehicleIds): array
                {
                    return ['88888888-8888-4888-8888-888888888888'];
                }
            },
            new GpsIngestionConfig(100, 500, 500, 500, 120),
            new NullLogger(),
        );

        $known = $this->buildCoordinate('ext-known', '2026-01-01T12:00:00+00:00');
        $unknown = $this->buildCoordinate('ext-unknown', '2026-01-01T12:01:00+00:00');
        $unknown->vehicleId = '12121212-1212-4121-8121-121212121212';

        try {
            $handler->handle(new IngestGpsCoordinateBatchCommand([$known, $unknown]));
            self::fail('Expected UnknownVehicleIdsException to be thrown.');
        } catch (UnknownVehicleIdsException $exception) {
            self::assertSame(['12121212-1212-4121-8121-121212121212'], $exception->unknownVehicleIds());
            self::assertSame('https://api-platform.com/errors/unknown-vehicle-id', $exception->getType());
            self::assertStringContainsString('12121212-1212-4121-8121-121212121212', (string) $exception->getDetail());
        }

        self::assertSame(0, $publisher->publishBatchCalls);
    }

    public function testMultipleUnknownVehicleIdsAreReportedInExceptionContract(): void
    {
        $publisher = new class implements GpsMessagePublisherInterface {
            public int $publishBatchCalls = 0;

            public function publish(array $payload): void
            {
            }

            public function publishBatch(array $payloads): void
            {
                ++$this->publishBatchCalls;
            }
        };

        $handler = new IngestGpsCoordinateBatchHandler(
            $publisher,
            new class implements VehicleCatalogInterface {
                public function findKnownVehicleIds(array $vehicleIds): array
                {
                    return ['88888888-8888-4888-8888-888888888888'];
                }
            },
            new GpsIngestionConfig(100, 500, 500, 500, 120),
            new NullLogger(),
        );

        $unknownA = $this->buildCoordinate('ext-unknown-a', '2026-01-01T12:01:00+00:00');
        $unknownA->vehicleId = '14141414-1414-4141-8141-141414141414';

        $unknownB = $this->buildCoordinate('ext-unknown-b', '2026-01-01T12:02:00+00:00');
        $unknownB->vehicleId = '15151515-1515-4151-8151-151515151515';

        try {
            $handler->handle(new IngestGpsCoordinateBatchCommand([$unknownA, $unknownB]));
            self::fail('Expected UnknownVehicleIdsException to be thrown.');
        } catch (UnknownVehicleIdsException $exception) {
            self::assertSame([
                '14141414-1414-4141-8141-141414141414',
                '15151515-1515-4151-8151-151515151515',
            ], $exception->unknownVehicleIds());
            self::assertSame('https://api-platform.com/errors/unknown-vehicle-id', $exception->getType());
            self::assertSame('Unknown vehicle identifier', $exception->getTitle());
            self::assertStringContainsString('14141414-1414-4141-8141-141414141414', (string) $exception->getDetail());
            self::assertStringContainsString('15151515-1515-4151-8151-151515151515', (string) $exception->getDetail());
        }

        self::assertSame(0, $publisher->publishBatchCalls);
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
