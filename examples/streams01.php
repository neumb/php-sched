<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;
use Neumb\Scheduler\Scheduler;

use function Neumb\Scheduler\delay;
use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\go;
use function Neumb\Scheduler\panic;
use function Neumb\Scheduler\socket_accept_;
use function Neumb\Scheduler\stream_read;
use function Neumb\Scheduler\stream_write;

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

/**
 * @param stdClass&object{clients:array<int,bool>} $state
 */
function client_worker(Socket $socket, object $state): void
{
    dprintfn('dispatched new client worker');
    $stream = socket_export_stream($socket);
    assert(is_resource($stream));

    while (true) {
        $data = stream_read($stream, 1024);
        if (false === $data) {
            panic('the stream has been unexpectedly closed');
        }

        if ('' === $data) {
            $sock = socket_import_stream($stream);
            assert($sock instanceof Socket);

            socket_getpeername($sock, $addr, $port);
            unset($state->clients[(int) $stream]); // @phpstan-ignore assign.propertyReadOnly

            assert(is_string($addr));
            assert(is_int($port));

            dprintfn('the client has disconnected [%s:%d]', $addr, $port);
            dprintfn('total connections: %d', count($state->clients));

            return;
        }

        stream_write($stream, $data);
    }
}

go(function (Socket $server, object $state): void {
    /**
     * @var stdClass&object{clients:array<int,bool>} $state
     */
    while (true) {
        $sock = socket_accept_($server);

        if (false === $sock) {
            panic('socket_accept: %s', socket_strerror(socket_last_error()));
        }

        $state->clients[(int) socket_export_stream($sock)] = true;

        socket_set_nonblock($sock);
        socket_getpeername($sock, $addr, $port);
        assert(is_string($addr));
        assert(is_int($port));
        dprintfn('a new client has connected [%s:%d]', $addr, $port);
        dprintfn('total connections: %d', count($state->clients));

        go(client_worker(...), $sock, $state);
    }
}, $server, (object) ['clients' => &$clients]);

go(function (): void {
    $tick = 0;
    while (true) { // @phpstan-ignore while.alwaysTrue
        delay(Duration::milliseconds(1000));
        dprintfn('%s', ($tick = 1 - $tick) ? 'tick' : 'tock');
    }
});

$tick = 1;

$sched->run();
