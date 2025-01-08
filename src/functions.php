<?php

/**
 * @template F of \Fiber<mixed,mixed,mixed,mixed>
 */
declare(strict_types=1);

namespace Neumb\Scheduler;

function dd(mixed ...$values): never
{
    foreach ($values as $v) {
        var_dump($v);
    }
    exit(1);
}

function panic(string $fmt = '', bool|float|int|string|null ...$args): never
{
    throw new \RuntimeException(sprintf($fmt, ...$args));
}

/**
 * @param \Fiber<mixed,mixed,mixed,mixed> ...$tasks
 *
 * @return \Fiber<mixed,mixed,mixed,mixed>
 */
function all(\Fiber ...$tasks): \Fiber
{
    return async(static function () use ($tasks): void {
        /** @var \SplQueue<\Fiber<mixed,mixed,mixed,mixed>> */
        $queue = new \SplQueue();

        foreach ($tasks as $task) {
            $queue->enqueue($task);
        }

        while (!$queue->isEmpty()) {
            $task = $queue->dequeue();

            if (!$task->isTerminated()) {
                if (!$task->isStarted()) {
                    $task->start();
                } else {
                    $task->resume();
                }
                $queue->enqueue($task);
                \Fiber::suspend();
            }
        }
    });
}

/**
 * @return \Fiber<mixed,mixed,mixed,mixed>
 */
function async(\Closure $closure): \Fiber
{
    return new \Fiber($closure);
}

/**
 * @template T of \Fiber<mixed,mixed,mixed,mixed>|\Closure(mixed):mixed
 *
 * @param T $task
 *
 * @return (T is \Fiber<mixed,mixed,mixed,mixed> ? T : \Fiber<mixed,mixed,mixed,mixed>)
 */
function wrapAsync(\Closure|\Fiber $task): \Fiber
{
    if ($task instanceof \Closure) {
        assert($task instanceof \Closure);

        return async($task);
    }

    return $task;
}

/**
 * @param \Fiber<mixed,mixed,mixed,mixed> $task
 */
function await(\Fiber $task, mixed ...$args): mixed
{
    $currentTask = \Fiber::getCurrent() ?? throw new \RuntimeException('Awaiting operation outside of the event loop is unsupported.');

    while (!$task->isTerminated()) {
        Scheduler::get()->enqueue(static function () use (&$currentTask) {
            advance($currentTask);
        });

        $currentTask->suspend();
        advance($task);
    }

    return $task->getReturn();
}

/**
 * @param \Fiber<mixed,mixed,mixed,mixed> $task
 */
function advance(\Fiber $task, mixed ...$args): void
{
    if (!$task->isStarted()) {
        $task->start(...$args);
    } elseif ($task->isSuspended() && !Scheduler::get()->isDelayed($task)) {
        $task->resume();
    }
}

function delay(Duration $time): void
{
    $fiber = \Fiber::getCurrent() ?? throw new \RuntimeException('Must be called within the running fiber.');

    Scheduler::get()->registerDelay($fiber);

    Scheduler::get()->defer($time, static function (int $start, int $now) use ($fiber) {
        Scheduler::get()->unregisterDelay($fiber);

        $fiber->resume();
    });

    $fiber->suspend();
}

function printf(string $fmt, bool|float|int|string|null ...$args): void
{
    fprintf(STDOUT, $fmt, ...$args);
}

function printfn(string $fmt, bool|float|int|string|null ...$args): void
{
    printf("{$fmt}\n", ...$args);
}

function dprintfn(string $fmt, bool|float|int|string|null ...$args): void
{
    printfn("[%04d]: {$fmt}", round((Scheduler::get()->getTime() - Scheduler::get()->getStart()) * 1e-6) | 0, ...$args);
}
