<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

final class BufferedGpsMessage
{
    /**
     * @param \Closure():void     $ack
     * @param \Closure(bool):void $reject
     */
    public function __construct(
        public readonly string $body,
        private readonly \Closure $ack,
        private readonly \Closure $reject,
    ) {
    }

    public function acknowledge(): void
    {
        ($this->ack)();
    }

    public function reject(bool $requeue = false): void
    {
        ($this->reject)($requeue);
    }
}
