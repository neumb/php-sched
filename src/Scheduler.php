<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final class Scheduler
{
    private static self $instance;
    /** @var \Fiber<void,void,void,void> */
    private \Fiber $mainLoopFiber;
    /** @var \SplQueue<\Fiber<mixed,mixed,mixed,mixed>> */
    private \SplQueue $queue;
    /** @var \WeakMap<\Fiber<mixed,mixed,mixed,mixed>,bool> */
    private \WeakMap $delayedTasks;

    private int $time = 0;
    private int $start = 0;
    private bool $running = false;

    private TimerList $timers;
    private SubscriptionList $readStreams;
    private SubscriptionList $writeStreams;

    private function __construct(
        private Clock $clock = new HighResolutionClock(),
    ) {
        $this->queue = new \SplQueue();
        $this->timers = TimerList::new();
        $this->delayedTasks = new \WeakMap();
        $this->readStreams = SubscriptionList::new();
        $this->writeStreams = SubscriptionList::new();
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function tick(): void
    {
        $this->time = $this->clock->now();
        $this->timers->tick(Duration::nanoseconds($this->time));
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed> $task
     */
    public function registerDelay(\Fiber $task): void
    {
        $this->delayedTasks[$task] = true;
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed> $task
     */
    public function unregisterDelay(\Fiber $task): void
    {
        unset($this->delayedTasks[$task]);
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed> $task
     */
    public function isDelayed(\Fiber $task): bool
    {
        return $this->delayedTasks[$task] ?? false;
    }

    public function run(): void
    {
        $this->mainLoopFiber ??= new \Fiber($this->mainLoop(...));

        $this->mainLoopFiber->isStarted()
            ? $this->mainLoopFiber->resume()
            : $this->mainLoopFiber->start();
    }

    private function mainLoop(): void
    {
        try {
            $this->start = $this->clock->now();
            $this->running = true;

            while ($this->cycle()) {
            }
        } finally {
            $this->running = false;
        }
    }

    private function advanceQueueTasks(): void
    {
        if ($this->queue->isEmpty()) {
            return;
        }

        $task = $this->queue->dequeue();
        advance($task);

        if (!$task->isTerminated()) {
            $this->queue->enqueue($task);
        }
    }

    private function advanceTimers(Duration &$timeout, bool &$yield): void
    {
        if ($this->timers->isEmpty()) {
            $timeout = Duration::zero();
            $yield = false;

            return;
        }

        $nearTimer = $this->timers->top();
        if (!$nearTimer->isDue(Duration::nanoseconds($this->time))) {
            $timeout = $nearTimer->left(Duration::nanoseconds($this->time));
            $yield = false;

            return;
        }

        $timer = $this->timers->shift();

        $task = async($timer->callback);
        advance($task, $this->start, $this->time);

        if ($timer->recurrent) {
            if ($task->isTerminated() && false !== $task->getReturn()) { // re-schedule the timer until it returns false
                $this->timers->add($timer->withSince(Duration::nanoseconds($this->time)));
            } elseif (!$task->isTerminated()) {
                $this->enqueue($task); // enqueue the timer task to the tasks queue
            }
        }

        $timeout = Duration::zero();
        $yield = true;
    }

    private function advanceStreamSubscriptions(Duration $timeout, bool &$yield): void
    {
        [$r, $w, $ex] = [$this->readStreams->asStreams(), $this->writeStreams->asStreams(), null];

        if (empty($r) && empty($w)) {
            $yield = false;

            return;
        }

        $n = stream_select($r, $w, $ex, 0, $timeout->asMicroseconds());

        if (false === $n) {
            panic('stream_select: failed');
        }

        $yield = true;
        if ($n < 1) {
            return;
        }

        foreach ($r as $stream) {
            $subs = $this->readStreams->forStream($stream);

            foreach ($subs as $sub) {
                advance($sub->task, $stream, $this->start, $this->time);

                if ($sub->task->isTerminated()) {
                    $this->readStreams->remove($sub);
                }
            }
        }

        foreach ($w as $stream) {
            $subs = $this->writeStreams->forStream($stream);

            foreach ($subs as $sub) {
                advance($sub->task, $stream, $this->start, $this->time);

                if ($sub->task->isTerminated()) {
                    $this->writeStreams->remove($sub);
                }
            }
        }
    }

    private function cycle(): bool
    {
        $this->tick();

        $this->advanceQueueTasks();

        $timeout = Duration::zero();
        $yield = false;

        $this->advanceTimers($timeout, $yield);
        if ($yield) {
            return true;
        }

        $this->advanceStreamSubscriptions($timeout, $yield);
        if ($yield) {
            return true;
        }

        if ($timeout->asMicroseconds() > 0) {
            usleep($timeout->asMicroseconds());
        } elseif ($this->queue->isEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed>|\Closure(mixed):mixed $task
     */
    public function enqueue(\Closure|\Fiber $task): void
    {
        $this->queue->enqueue(wrapAsync($task));
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed>|\Closure(mixed):mixed $task
     */
    public function onSocketReadable(\Socket $socket, \Fiber|\Closure $task): void
    {
        $stream = socket_export_stream($socket);

        $this->readStreams->add(new StreamSubscription($stream, wrapAsync($task)));
    }

    /**
     * @param resource                                              $stream
     * @param \Fiber<mixed,mixed,mixed,mixed>|\Closure(mixed):mixed $task
     */
    public function onStreamReadable(mixed $stream, \Fiber|\Closure $task): void
    {
        $this->readStreams->add(new StreamSubscription($stream, wrapAsync($task)));
    }

    /**
     * @param resource                                              $stream
     * @param \Fiber<mixed,mixed,mixed,mixed>|\Closure(mixed):mixed $task
     */
    public function onStreamWritable(mixed $stream, \Fiber|\Closure $task): void
    {
        $this->writeStreams->add(new StreamSubscription($stream, wrapAsync($task)));
    }

    public function defer(Duration $timeout, \Closure $callback): void
    {
        $this->timers->add(Timer::new(
            interval: $timeout,
            since: Duration::nanoseconds($this->clock->now()),
            callback: $callback
        ));
    }

    public function repeat(Duration $interval, \Closure $callback): void
    {
        $this->timers->add(Timer::recurrent(
            interval: $interval,
            since: Duration::nanoseconds($this->clock->now()),
            callback: $callback,
        ));
    }
}
