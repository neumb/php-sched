<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

/**
 * @template T
 */
final class Channel
{
    /**
     * @var \SplQueue<T>
     */
    private readonly \SplQueue $queue;

    private bool $isClosed;

    private function __construct()
    {
        $this->queue = new \SplQueue();
        $this->isClosed = false;
    }

    /**
     * @return Channel<mixed>
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * @return T
     */
    public function receive(): mixed
    {
        $fiber = \Fiber::getCurrent() ?? panic('receive from channel outside runtime');

        Runtime::get()->markChanAwaiting($fiber);

        while (! $this->isClosed() && $this->queue->isEmpty()) {
            $fiber->suspend();
        }

        Runtime::get()->unmarkChanAwaiting($fiber);

        return $this->queue->dequeue();
    }

    /**
     * @param T $data
     */
    public function send(mixed $data): void
    {
        $fiber = \Fiber::getCurrent() ?? panic('send to channel outside runtime');

        if ($this->isClosed()) {
            panic('send to closed channel');
        }

        Runtime::get()->markChanAwaiting($fiber);

        while (! $this->queue->isEmpty()) {
            $fiber->suspend();
        }

        Runtime::get()->unmarkChanAwaiting($fiber);

        $this->queue->enqueue($data);
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function close(): void
    {
        if ($this->isClosed) {
            panic('close of closed channel');
        }

        $this->isClosed = true;
    }
}
