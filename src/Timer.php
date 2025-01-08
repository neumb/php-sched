<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final class Timer
{
    private function __construct(
        public readonly Duration $interval,
        public readonly Duration $since,
        public readonly \Closure $callback,
        public readonly bool $recurrent = false,
    ) {
    }

    public static function new(
        Duration $interval,
        Duration $since,
        \Closure $callback,
    ): self {
        return new self(
            interval: $interval,
            since: $since,
            callback: $callback,
            recurrent: false,
        );
    }

    public static function recurrent(
        Duration $interval,
        Duration $since,
        \Closure $callback,
    ): self {
        return new self(
            interval: $interval,
            since: $since,
            callback: $callback,
            recurrent: true
        );
    }

    public function withSince(Duration $since): self
    {
        return new self(
            interval: $this->interval,
            since: $since,
            callback: $this->callback,
            recurrent: $this->recurrent,
        );
    }

    public function isDue(Duration $time): bool
    {
        return ($time->duration - $this->since->duration) >= $this->interval->duration;
    }

    public function left(Duration $time): Duration
    {
        $passed = min($time->duration - $this->since->duration, $this->interval->duration);

        return Duration::nanoseconds(max($this->interval->duration - $passed, 0));
    }
}
