<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final class HighResolutionClock implements Clock
{
    public function now(): int
    {
        return hrtime(true);
    }
}
