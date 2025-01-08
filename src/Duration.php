<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final class Duration
{
    private function __construct(
        public readonly int $duration,
    ) {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function nanoseconds(int|float $time): self
    {
        return new self((int) $time);
    }

    public static function microseconds(int|float $time): self
    {
        return new self(round($time * 1e3) | 0);
    }

    public function asMicroseconds(): int
    {
        return (int) ($this->duration * 1e-3);
    }

    public static function milliseconds(int|float $time): self
    {
        return new self(round($time * 1e6) | 0);
    }

    public function asMilliseconds(): int
    {
        return (int) ($this->duration * 1e-6);
    }

    public static function seconds(int|float $time): self
    {
        return new self(round($time * 1e9) | 0);
    }

    public function asNanoseconds(): int
    {
        return $this->duration;
    }
}
