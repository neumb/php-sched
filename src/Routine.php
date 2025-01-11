<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final readonly class Routine
{
    /**
     * @template F of \Fiber
     *
     * @param F                       $routine
     * @param array<int|string,mixed> $args
     */
    public function __construct(
        public \Fiber $routine,
        public array $args,
    ) {
    }
}
