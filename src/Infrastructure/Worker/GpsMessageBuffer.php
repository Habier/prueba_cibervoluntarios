<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

use App\Application\Config\GpsIngestionConfig;
use App\Application\Service\ProcessGpsMessageBatchHandler;
use Psr\Log\LoggerInterface;

final class GpsMessageBuffer
{
    /**
     * @var list<BufferedGpsMessage>
     */
    private array $messages = [];

    private ?float $firstBufferedAt = null;

    public function __construct(
        private readonly ProcessGpsMessageBatchHandler $handler,
        private readonly GpsIngestionConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function add(BufferedGpsMessage $message): void
    {
        $this->messages[] = $message;
        $this->firstBufferedAt ??= microtime(true);

        if (count($this->messages) >= $this->config->batchSize) {
            $this->flush('batch_size');
        }
    }

    public function flushIfTimedOut(): void
    {
        if ($this->messages === [] || $this->firstBufferedAt === null) {
            return;
        }

        $elapsedMs = (microtime(true) - $this->firstBufferedAt) * 1000;

        if ($elapsedMs >= $this->config->flushTimeoutMs) {
            $this->flush('timeout');
        }
    }

    public function flush(string $reason = 'manual'): void
    {
        if ($this->messages === []) {
            return;
        }

        $messages = $this->messages;

        try {
            $result = $this->handler->handle($messages, $reason);

            foreach ($result->invalidMessages as $message) {
                $message->acknowledge();
            }

            foreach ($result->validMessages as $message) {
                $message->acknowledge();
            }

            $this->logger->info('GPS batch flushed.', [
                'worker_name' => 'worker-gps',
                'batch_size' => count($messages),
                'flush_reason' => $reason,
                'insert_duration_ms' => $result->insertDurationMs,
                'ack_count' => count($result->validMessages) + count($result->invalidMessages),
                'nack_count' => 0,
                'invalid_message_count' => count($result->invalidMessages),
                'coordinates_processed_total' => $result->processedCount,
            ]);
        } catch (\Throwable $throwable) {
            $this->logger->error('GPS batch flush failed.', [
                'worker_name' => 'worker-gps',
                'batch_size' => count($messages),
                'flush_reason' => $reason,
                'ack_count' => 0,
                'nack_count' => count($messages),
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        } finally {
            $this->messages = [];
            $this->firstBufferedAt = null;
        }
    }
}
