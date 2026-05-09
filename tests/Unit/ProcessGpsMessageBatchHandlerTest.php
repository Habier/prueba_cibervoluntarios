<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Port\DlqPublisherInterface;
use App\Application\Port\DomainEventDispatcherInterface;
use App\Application\Port\ObservationIdempotencyPortInterface;
use App\Application\Port\VehicleWriteRepositoryInterface;
use App\Application\Service\ProcessGpsMessageBatchHandler;
use App\Domain\Alert\AlertEvaluationPolicyInterface;
use App\Domain\Vehicle\LatestPositionPolicyInterface;
use App\Domain\Vehicle\ValueObject\VehicleId;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleWriteOutcome;
use App\Infrastructure\Worker\BufferedGpsMessage;
use PHPUnit\Framework\TestCase;

final class ProcessGpsMessageBatchHandlerTest extends TestCase
{
    public function testInvalidMessagesAreSentToDlq(): void
    {
        $dlqCalls = [];
        $handler = new ProcessGpsMessageBatchHandler(
            new class implements ObservationIdempotencyPortInterface {
                public function claim(\App\Domain\Gps\GpsCoordinate $observation): bool
                {
                    return true;
                }
            },
            new class implements VehicleWriteRepositoryInterface {
                public function loadForUpdate(VehicleId $vehicleId): Vehicle
                {
                    return new Vehicle($vehicleId, null);
                }

                public function save(VehicleWriteOutcome $outcome): void
                {
                }
            },
            new class implements AlertEvaluationPolicyInterface {
                public function evaluate(Vehicle $vehicle, \App\Domain\Gps\GpsCoordinate $observation): array
                {
                    return [];
                }
            },
            new class implements LatestPositionPolicyInterface {
                public function shouldReplace(?\App\Domain\Vehicle\LastKnownPosition $current, \App\Domain\Gps\GpsCoordinate $candidate): bool
                {
                    return true;
                }
            },
            new class implements DomainEventDispatcherInterface {
                public function dispatch(array $events): void
                {
                }
            },
            new class($dlqCalls) implements DlqPublisherInterface {
                /**
                 * @var list<array{0:array<string, mixed>,1:string}>
                 */
                public array $calls = [];

                /**
                 * @param list<array{0:array<string, mixed>,1:string}> $calls
                 */
                public function __construct(array &$calls)
                {
                    $this->calls = &$calls;
                }

                public function publish(array $payload, string $reason): void
                {
                    $this->calls[] = [$payload, $reason];
                }
            },
        );

        $result = $handler->handle([
            new BufferedGpsMessage('{bad json', static function (): void {}, static function (): void {}),
        ], 'manual');

        self::assertCount(1, $result->invalidMessages);
    }
}
