<?php

declare(strict_types=1);

namespace Neumb\Async;

use Fiber;

function dd(...$values) {
	foreach ($values as $v) {
		var_dump($v);
	}
	exit(1);
}

function all(Fiber $task, Fiber ...$tasks): Fiber
{
	$tasks = [$task, ...$tasks];

	return async(static function () use ($tasks): void {
		$queue = new \SplQueue;

		foreach ($tasks as $task) {
			$queue->enqueue($task);
		}

		while (! $queue->isEmpty()) {
			$task = $queue->dequeue();

			if (! $task->isTerminated()) {
				if (! $task->isStarted()) {
					$task->start();
				} else {
					$task->resume();
				}
				$queue->enqueue($task);
				Fiber::suspend();
			}
		}
	});
}

function async(\Closure $closure): Fiber
{
	return new Fiber($closure);
}

function advance(Fiber $task, mixed ...$args): void
{
	if (! $task->isStarted()) {
		$task->start(...$args);
	} else if ($task->isSuspended() && ! Loop::get()->isDelayed($task)) {
		$task->resume(...$args);
	}
}

function delay(Duration $time): void {
	$fiber = Fiber::getCurrent() ?? throw new \RuntimeException('Must be called within the running fiber.');

	Loop::get()->registerDelay($fiber);

	Loop::get()->defer($time, static function (int $start, int $now) use ($fiber) {
		Loop::get()->unregisterDelay($fiber);

		$fiber->resume();
	});

	$fiber->suspend();
}

function printf(string $fmt, ...$args): void
{
	fprintf(STDOUT, $fmt, ...$args);
}

function printfn(string $fmt, ...$args): void
{
	printf("{$fmt}\n", ...$args);
}

function dprintfn(string $fmt, ...$args): void
{
	printfn("[%04d]: {$fmt}", round((Loop::get()->time - Loop::get()->start)*1e-6) | 0, ...$args);
}


final class Duration {
	private function __construct(
		public readonly int $duration,
	) {
	}

	public int $nanoseconds {
		get => $this->duration;
	}

	public int $microseconds {
		get => (int)($this->duration * 1e-3);
	}

	public static function zero(): self
	{
		return new self(0);
	}

	public static function nanoseconds(int|float $time): self {
		return new self((int)$time);
	}

	public static function microseconds(int|float $time): self {
		return new self(round($time * 1e3) | 0);
	}

	public static function milliseconds(int|float $time): self {
		return new self(round($time * 1e6) | 0);
	}

	public static function seconds(int|float $time): self {
		return new self(round($time * 1e9) | 0);
	}
}

final class SubscriptionList
{
	private function __construct(
		private array $subscriptions = [],
	) {
	}

	public static function new(): self
	{
		return new self;
	}

	public function add(StreamSubscription $sub): void
	{
		$this->subscriptions[(int)$sub->stream] = $sub;
	}

	public function remove(mixed $stream): void
	{
		unset($this->subscriptions[(int)$stream]);
	}

	public function get(mixed $stream): StreamSubscription
	{
		return $this->subscriptions[(int)$stream];
	}

	public function asStreams(): array
	{
		return array_map(static fn (StreamSubscription $sub): mixed => $sub->stream, $this->subscriptions);
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

final readonly class StreamSubscription
{
	public mixed $stream;

	public function __construct(mixed $stream, public \Closure $callback)
	{
		if (! is_resource($stream)) {
			throw new \InvalidArgumentException(
				sprintf('Stream argument must be a resource, %s given.', get_debug_type($stream))
			);
		}

		$this->stream = $stream;
	}
}

interface Clock
{
	public function now(): int; // nanoseconds
}

final class HighResolutionClock implements Clock
{
	public function now(): int
	{
		return hrtime(true);
	}
}

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
		$passed = min(($time->duration - $this->since->duration), $this->interval->duration);

		return Duration::nanoseconds(max($this->interval->duration - $passed, 0));
	}
}

final class Timers
{
	private function __construct(
		private array $timers = [],
	) {}

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
			throw new \RuntimeException("The timers list is empty.");
		}

		return $this->timers[0];
	}

	public function shift(): Timer
	{
		if ($this->isEmpty()) {
			throw new \RuntimeException("The timers list is empty.");
		}

		$timer = array_shift($this->timers);
		$this->timers = array_values($this->timers);

		return $timer;
	}
}

final class Loop
{
	private static Loop $instance;
	private Fiber $loopFiber;
	private Timers $timers;
	private \SplQueue $queue;
	private \WeakMap $delayedTasks;

	public private(set) int $time = 0;
	public private(set) int $start = 0;
	public private(set) bool $running = false;

	private SubscriptionList $readStreams;
	private SubscriptionList $writeStreams;

	private function __construct(
		private Clock $clock = new HighResolutionClock,
	) {
		$this->queue = new \SplQueue;
		$this->timers = Timers::new();
		$this->delayedTasks = new \WeakMap;
		$this->readStreams = SubscriptionList::new(); 
		$this->writeStreams = SubscriptionList::new();
	}

	public static function get(): Loop
	{
		return self::$instance ??= new self;
	}

	public function tick(): void
	{
		$this->time = $this->clock->now();
		$this->timers->tick(Duration::nanoseconds($this->time));
	}

	public function registerDelay(Fiber $fiber): void
	{
		$this->delayedTasks[$fiber] = true;
	}

	public function unregisterDelay(Fiber $fiber): void
	{
		unset($this->delayedTasks[$fiber]);
	}

	public function isDelayed(Fiber $task): bool
	{
		return $this->delayedTasks[$task] ?? false;
	}

	public function run(): void
	{
		$this->loopFiber ??= new Fiber($this->mainLoop(...));

		$this->loopFiber->isStarted() 
			? $this->loopFiber->resume()
			: $this->loopFiber->start();
	}

	private function mainLoop(): void
	{
		try {
			$this->start = $this->clock->now();
			$this->running = true;

			while ($this->cycle());
		} finally {
			$this->running = false;
		}
	}

	private function advanceQueueTasks(): void
	{
		if (! $this->queue->isEmpty()) {
			$task = $this->queue->dequeue();
			advance($task);

			if (! $task->isTerminated()) {
				$this->queue->enqueue($task);
			}
		}
	}

	private function advanceTimers(Duration &$timeout, bool &$yield): void
	{
		if ($this->timers->isEmpty()) {
			$timeout = Duration::zero(); $yield = false;

			return;
		}

		$nearTimer = $this->timers->top();
		if (! $nearTimer->isDue(Duration::nanoseconds($this->time))) {
			$timeout = $nearTimer->left(Duration::nanoseconds($this->time)); $yield = false;

			return;
		}

		$timer = $this->timers->shift();

		$task = async($timer->callback);
		advance($task, $this->start, $this->time);

		if ($timer->recurrent) {
			if ($task->isTerminated() && $task->getReturn() !== false) { // re-schedule the timer until it returns false
				$this->timers->add($timer->withSince(Duration::nanoseconds($this->time)));
			} else if (! $task->isTerminated()) {
				$this->queue->enqueue($task); // enqueue the timer task to the tasks queue
			}
		}

		$timeout = Duration::zero(); $yield = true;
	}

	private function advanceStreamSubscriptions(Duration $timeout, bool &$yield): void
	{
		[$r, $w, $ex] = [$this->readStreams->asStreams(), $this->writeStreams->asStreams(), null];

		if (empty($r) && empty($w)) {
			$yield = false;

			return;
		}

		$n = stream_select($r, $w, $ex, 0, $timeout->microseconds);

		if ($n === false) {
			throw new \RuntimeException('Stream select has failed.');
		}

		if ($n < 1) {
			$yield = true;

			return;
		}

		foreach ($r as $res) {
			$sub = $this->readStreams->get($res);
			$task = async($sub->callback);

			$this->readStreams->remove($res);

			advance($task, $res, $this->start, $this->time);

			if (! $task->isTerminated()) {
				$this->queue->enqueue($task);
			}
		}

		foreach ($w as $res) {
			$sub = $this->writeStreams->get($res);
			$task = async($sub->callback);

			$this->writeStreams->remove($res);

			advance($task, $res, $this->start, $this->time);

			if (! $task->isTerminated()) {
				$this->queue->enqueue($task);
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

		if ($timeout->microseconds > 0) {
			usleep($timeout->microseconds);
		} else {
			return false;
		}

		return true;
	}

	public function nextTick(\Closure|Fiber $task): void
	{
		if (! $task instanceof Fiber) {
			$task = async($task);
		}
		$this->queue->enqueue($task);
	}

	public function onStreamReadable(mixed $stream, \Closure $callback): void
	{
		$this->readStreams->add(new StreamSubscription($stream, $callback));
	}

	public function onStreamWritable(mixed $stream, \Closure $callback): void
	{
		$this->writeStreams->add(new StreamSubscription($stream, $callback));
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

$loop = Loop::get();

$loop->defer(Duration::milliseconds(1200), static function (int $start, int $now) {
	$diff = (int)($now-$start)*1e-6;
	dprintfn("The deferred task has executed after %dms", $diff);
});

$loop->repeat(Duration::milliseconds(300), static function (int $start, int $now) {
	static $times = 0;
	$times++;

	dprintfn("The recurrent timer has run %d times", $times);

	if ($times >= 9) {
		return false; 
	}
});

touch("sample.txt");
$stream = fopen("sample.txt", "r"); stream_set_blocking($stream, false);

$result = '';

$loop->onStreamReadable($stream, static function (mixed $stream) use (&$result) {
	$start = hrtime(true);
	dprintfn("The stream has become readable");

	do {
		delay(Duration::milliseconds(100));
		$read = fread($stream, 1);
		$result .= $read;
		dprintfn("The read stream task has resumed after %dms", (int)((hrtime(true)-$start) * 1e-6));

		Fiber::suspend();
	} while (!feof($stream));

	$result = trim($result);
});

$loop->run();

dd($result);
