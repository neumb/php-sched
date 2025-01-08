<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final class TimerList
{
    /**
     * @param array<int,Timer> $timers
     */
    private function __construct(
        private array $timers = [],
    ) {
    }

    public static function new(): self
    {
        return new self();
    }

    public function tick(Duration $time): void
    {
        usort($this->timers, static fn (Timer $a, Timer $b): int => $a->left($time)->duration <=> $b->left($time)->duration);
    }

    public function add(Timer $timer): void
    {
        $this->timers[] = $timer;
    }

    public function isEmpty(): bool
    {
        return empty($this->timers);
    }

    public function top(): Timer
    {
        if ($this->isEmpty()) {
            throw new \RuntimeException('The timers list is empty.');
        }

        return $this->timers[0];
    }

    public function shift(): Timer
    {
        if ($this->isEmpty()) {
            throw new \RuntimeException('The timers list is empty.');
        }

        $timer = array_shift($this->timers);
        $this->timers = array_values($this->timers);

        assert($timer instanceof Timer);

        return $timer;
    }
}
