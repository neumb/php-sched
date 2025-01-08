<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Scheduler;

use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\panic;

const SERVER_HOST = '127.0.0.1';
const SERVER_PORT = 8019;

/** @var array<int,WeakReference<Socket>> */
$clients = [];

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

dprintfn('listening to %s:%s...', SERVER_HOST, SERVER_PORT);

$sched = Scheduler::get();

dprintfn('total clients: %d', count($clients));

$sched->onSocketReadable($server, static function () use ($sched, $server, &$clients): void {
    while (true) {
        $sock = socket_accept($server);

        if (false === $sock) {
            panic('socket_accept: %s', socket_strerror(socket_last_error()));
        }
        $clients[(int) socket_export_stream($sock)] = true;

        socket_set_nonblock($sock);
        socket_getpeername($sock, $addr, $port);
        assert(is_string($addr));
        assert(is_int($port));
        dprintfn('a new client has connected [%s:%d]', $addr, $port);
        dprintfn('total connections: %d', count($clients));

        $sched->onSocketReadable($sock, static function (mixed $stream) use ($sched, &$clients): void {
            assert(is_resource($stream));
            while (true) {
                $data = fread($stream, 1024);
                if (false === $data) {
                    panic('the stream has been unexpectedly closed');
                }

                if ('' === $data) {
                    $sock = socket_import_stream($stream);
                    assert($sock instanceof Socket);

                    socket_getpeername($sock, $addr, $port);
                    unset($clients[(int) $stream]);

                    assert(is_string($addr));
                    assert(is_int($port));
                    dprintfn('the client has disconnected [%s:%d]', $addr, $port);
                    dprintfn('total connections: %d', count($clients));

                    return;
                }

                $sched->onStreamWritable($stream, static function (mixed $stream) use ($data): void {
                    assert(is_resource($stream));
                    if (false === fwrite($stream, $data)) {
                        panic('the stream has been unexpectedly closed');
                    }
                });

                Fiber::suspend();
            }
        });

        Fiber::suspend();
    }
});

$sched->run();
