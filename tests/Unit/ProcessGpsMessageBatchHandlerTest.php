<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Port\DlqPublisherInterface;
use App\Application\Port\GpsBatchPersisterInterface;
use App\Application\Service\ProcessGpsMessageBatchHandler;
use App\Infrastructure\Persistence\Doctrine\Gps\GpsBatchPersistenceResult;
use App\Infrastructure\Worker\BufferedGpsMessage;
use PHPUnit\Framework\TestCase;

final class ProcessGpsMessageBatchHandlerTest extends TestCase
{
    public function testInvalidMessagesAreSentToDlq(): void
    {
        $dlqCalls = [];
        $handler = new ProcessGpsMessageBatchHandler(
            new class implements GpsBatchPersisterInterface {
                public function persist(array $coordinates): GpsBatchPersistenceResult
                {
                    return new GpsBatchPersistenceResult($coordinates);
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
