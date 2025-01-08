<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final readonly class StreamSubscription
{
    /** @var resource */
    public mixed $stream;

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed> $task
     */
    public function __construct(mixed $stream, public \Fiber $task)
    {
        if (! is_resource($stream)) {
            throw new \InvalidArgumentException(sprintf('Stream argument must be a resource, %s given.', get_debug_type($stream)));
        }

        $this->stream = $stream;
    }
}
