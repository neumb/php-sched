<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

interface Clock
{
    public function now(): int; // nanoseconds
}
