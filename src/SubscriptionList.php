<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final class SubscriptionList
{
    /**
     * @param array<int,\WeakMap<StreamSubscription,StreamSubscription>> $subscriptions
     */
    private function __construct(
        private array $subscriptions = [],
    ) {
    }

    public static function new(): self
    {
        return new self();
    }

    public function add(StreamSubscription $sub): void
    {
        /** @var \WeakMap<StreamSubscription,StreamSubscription> */
        $map = new \WeakMap();

        $this->subscriptions[(int) $sub->stream] ??= $map;
        $this->subscriptions[(int) $sub->stream][$sub] = $sub;
    }

    public function remove(StreamSubscription $sub): void
    {
        if (! isset($this->subscriptions[(int) $sub->stream])) {
            return;
        }
        unset($this->subscriptions[(int) $sub->stream][$sub]);
    }

    /**
     * @param resource $stream
     *
     * @return iterable<StreamSubscription>
     */
    public function forStream(mixed $stream): iterable
    {
        if (! is_resource($stream)) {
            panic('expected argument of type resource, %s given', get_debug_type($stream));
        }
        foreach ($this->subscriptions[(int) $stream] as $sub) {
            yield $sub;
        }
    }

    /**
     * @return list<resource>
     */
    public function asStreams(): array
    {
        $streams = [];

        foreach ($this->subscriptions as $subs) {
            foreach ($subs as $s) {
                $streams[] = $s->stream;
                break;
            }
        }

        return $streams;
    }

    public function isEmpty(): bool
    {
        return empty($this->subscriptions);
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }
}
