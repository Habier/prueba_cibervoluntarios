<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Config\GpsIngestionConfig;
use App\Application\Service\ProcessBatchResult;
use App\Application\Service\ProcessGpsMessageBatchHandler;
use App\Infrastructure\Worker\BufferedGpsMessage;
use App\Infrastructure\Worker\GpsMessageBuffer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GpsMessageBufferTest extends TestCase
{
    public function testFlushesOnBatchSize(): void
    {
        $handler = new class extends ProcessGpsMessageBatchHandler {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function handle(array $messages, string $reason): ProcessBatchResult
            {
                ++$this->calls;

                return new ProcessBatchResult($messages, [], count($messages), 1.0);
            }
        };

        $buffer = new GpsMessageBuffer($handler, new GpsIngestionConfig(2, 500, 500, 500, 120), new NullLogger());
        $acked = 0;
        $buffer->add(new BufferedGpsMessage('{"ok":1}', static function () use (&$acked): void { ++$acked; }, static function (): void {}));
        $buffer->add(new BufferedGpsMessage('{"ok":2}', static function () use (&$acked): void { ++$acked; }, static function (): void {}));

        self::assertSame(1, $handler->calls);
        self::assertSame(2, $acked);
    }

    public function testFlushesOnTimeout(): void
    {
        $handler = new class extends ProcessGpsMessageBatchHandler {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function handle(array $messages, string $reason): ProcessBatchResult
            {
                ++$this->calls;

                return new ProcessBatchResult($messages, [], count($messages), 1.0);
            }
        };

        $buffer = new GpsMessageBuffer($handler, new GpsIngestionConfig(100, 0, 500, 500, 120), new NullLogger());
        $buffer->add(new BufferedGpsMessage('{"ok":1}', static function (): void {}, static function (): void {}));
        $buffer->flushIfTimedOut();

        self::assertSame(1, $handler->calls);
    }

    public function testNoAckIfHandlerFails(): void
    {
        $handler = new class extends ProcessGpsMessageBatchHandler {
            public function __construct()
            {
            }

            public function handle(array $messages, string $reason): ProcessBatchResult
            {
                throw new \RuntimeException('db failed');
            }
        };

        $buffer = new GpsMessageBuffer($handler, new GpsIngestionConfig(1, 500, 500, 500, 120), new NullLogger());
        $acked = 0;

        try {
            $buffer->add(new BufferedGpsMessage('{"ok":1}', static function () use (&$acked): void { ++$acked; }, static function (): void {}));
        } catch (\RuntimeException) {
        }

        self::assertSame(0, $acked);
    }
}
