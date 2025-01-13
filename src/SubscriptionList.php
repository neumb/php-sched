<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

final class SubscriptionList
{
    /**
     * @param array<int,array<int,StreamSubscription>> $subscriptions
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
        $this->subscriptions[(int) $sub->stream] ??= [];
        $this->subscriptions[(int) $sub->stream][spl_object_id($sub)] = $sub;
    }

    public function remove(StreamSubscription $sub): void
    {
        if (! isset($this->subscriptions[(int) $sub->stream])) {
            return;
        }

        unset($this->subscriptions[(int) $sub->stream][spl_object_id($sub)]);
    }

    /**
     * @param resource $stream
     *
     * @return list<StreamSubscription>
     */
    public function forStream(mixed $stream): array
    {
        if (! is_resource($stream)) {
            panic('expected argument of type resource, %s given', get_debug_type($stream));
        }

        return array_values($this->subscriptions[(int) $stream]);
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
