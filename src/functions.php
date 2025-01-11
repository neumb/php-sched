<?php

declare(strict_types=1);

namespace Neumb\Scheduler;

function dump(mixed ...$values): void
{
    foreach ($values as $v) {
        var_dump($v);
    }
}

function dd(mixed ...$values): never
{
    dump(...$values);
    exit(1);
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
    $timePassed = Runtime::get()->getTime()->asMilliseconds() - Runtime::get()->getStart()->asMilliseconds();

    printfn("[%04d]: {$fmt}", $timePassed, ...$args);
}

function panic(string $fmt = '', bool|float|int|string|null ...$args): never
{
    throw new \RuntimeException(sprintf($fmt, ...$args));
}

/**
 * @template F of \Fiber
 *
 * @param F ...$tasks
 *
 * @return \Fiber<mixed,mixed,mixed,mixed>
 */
function all(\Fiber ...$tasks): \Fiber
{
    return async(static function () use ($tasks): void {
        /** @var \SplQueue<F> */
        $queue = new \SplQueue();

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
function async_wrap(\Closure|\Fiber $task): \Fiber
{
    if ($task instanceof \Closure) {
        assert($task instanceof \Closure);

        return async($task);
    }

    return $task;
}

/**
 * @template F of \Fiber
 *
 * @param F $task
 */
function await(\Fiber $task, mixed ...$args): mixed
{
    while (! $task->isTerminated()) {
        \Fiber::suspend();
    }

    return $task->getReturn();
}

function go(\Closure $task, mixed ...$args): void
{
    Runtime::get()->dispatchRoutine($task, ...$args);

    if (null !== \Fiber::getCurrent()) {
        \Fiber::suspend();
    }
}

/**
 * @param resource   $stream
 * @param int<1,max> $length
 *
 * @return \Fiber<void,void,string|false,void>
 */
function stream_read_async(mixed $stream, int $length): \Fiber
{
    /** @var \Fiber<void,void,string|false,void> */
    $deferred = async(static function (): mixed {
        return \Fiber::suspend();
    });
    $deferred->start();

    Runtime::get()->onStreamReadable($stream, static function (mixed $stream) use ($length, $deferred): void {
        assert(is_resource($stream));

        $deferred->resume(fread($stream, $length));
    });

    return $deferred;
}

/**
 * @param resource   $stream
 * @param int<1,max> $length
 */
function stream_read(mixed $stream, int $length): string|false
{
    return await(stream_read_async($stream, $length)); // @phpstan-ignore return.type
}

/**
 * @return \Fiber<void,void,\Socket|false,void>
 */
function socket_accept_async(\Socket $sock): \Fiber
{
    /** @var \Fiber<void,void,\Socket|false,void> */
    $deferred = async(static function (): mixed {
        return \Fiber::suspend();
    });
    $deferred->start();

    Runtime::get()->onSocketReadable($sock, static function (mixed $stream) use ($deferred): void {
        assert(is_resource($stream));
        $sock = socket_import_stream($stream);
        assert($sock instanceof \Socket);

        $acceptedSock = socket_accept($sock);
        if ($acceptedSock instanceof \Socket) {
            socket_set_nonblock($acceptedSock);
        }

        $deferred->resume($acceptedSock);
    });

    return $deferred;
}

function socket_accept_(\Socket $sock): \Socket|false
{
    return await(socket_accept_async($sock)); // @phpstan-ignore return.type
}

/**
 * @param resource $stream
 *
 * @return \Fiber<void,void,int|false,void>
 */
function stream_write_async(mixed $stream, string $buffer): \Fiber
{
    /** @var \Fiber<void,void,int|false,void> */
    $deferred = async(static function (): mixed {
        return \Fiber::suspend();
    });
    $deferred->start();

    Runtime::get()->onStreamWritable($stream, static function (mixed $stream) use ($deferred, $buffer): void {
        assert(is_resource($stream));

        $deferred->resume(fwrite($stream, $buffer));
    });

    return $deferred;
}

/**
 * @param resource $stream
 */
function stream_write(mixed $stream, string $buffer): int|false
{
    return await(stream_write_async($stream, $buffer)); // @phpstan-ignore return.type
}

/**
 * @return \Fiber<mixed,mixed,mixed,mixed>
 */
function current_fiber(): \Fiber
{
    return \Fiber::getCurrent() ?? throw new \RuntimeException('Must be called within the running fiber.');
}

function delay(Duration $time): void
{
    $fiber = current_fiber();

    Runtime::get()->markDelayed($fiber);

    Runtime::get()->defer($time, static function () use ($fiber) {
        Runtime::get()->unmarkDelayed($fiber);

        $fiber->resume();
    });

    $fiber->suspend();
}

function sched(): void
{
    current_fiber()->suspend();
}

function tcp_server_create(string $addr, int $port): \Socket
{
    $server = socket_create(
        /*
         * Communication Domain.
         * IPv4 Internet Protocols.
         */
        AF_INET,
        /*
         * Socket Type.
         * The SOCK_STREAM provides sequenced, two-way, connection-based byte streams.
         */
        SOCK_STREAM,
        /*
         * Protocol.
         * Transmission Control Protocol.
         */
        SOL_TCP,
    );

    if (false === $server) {
        panic('socket_create: %s', socket_strerror(socket_last_error()));
    }

    socket_set_nonblock($server);

    socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);

    if (false === socket_bind($server, SERVER_HOST, SERVER_PORT)) {
        panic('socket_bind: %s', socket_strerror(socket_last_error()));
    }

    if (false === socket_listen($server)) {
        panic('socket_listen: %s', socket_strerror(socket_last_error()));
    }

    return $server;
}
