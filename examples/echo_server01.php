<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;

use function Neumb\Scheduler\delay;
use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\go;
use function Neumb\Scheduler\panic;
use function Neumb\Scheduler\socket_accept_;
use function Neumb\Scheduler\stream_read;
use function Neumb\Scheduler\stream_write;
use function Neumb\Scheduler\tcp_server_create;

const SERVER_HOST = '127.0.0.1';
const SERVER_PORT = 8019;

final class State
{
    /** @var WeakMap<Socket,true> */
    public WeakMap $clients;

    public function __construct(
        public readonly Socket $server,
    ) {
        $this->clients = new WeakMap();
    }

    public function clients_add(Socket $sock): void
    {
        $this->clients[$sock] = true;
    }

    public function clients_del(Socket $sock): void
    {
        if (! isset($this->clients[$sock])) {
            throw new RuntimeException('The given socket is not set');
        }

        unset($this->clients[$sock]);
    }

    public function clients_count(): int
    {
        return count($this->clients);
    }
}

$state = new State(tcp_server_create(SERVER_HOST, SERVER_PORT));

dprintfn('listening to %s:%s...', SERVER_HOST, SERVER_PORT);

function client_worker(Socket $socket, State $state): void
{
    socket_getpeername($socket, $addr, $port);
    assert(is_string($addr));
    assert(is_int($port));
    dprintfn('a new client has connected [%s:%d]', $addr, $port);
    dprintfn('total connections: %d', count($state->clients));

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

            $state->clients_del($socket);

            dprintfn('the client has disconnected [%s:%d]', $addr, $port);
            dprintfn('total connections: %d', $state->clients_count());

            return;
        }

        stream_write($stream, $data);
    }
}

go(function (State $state): void {
    while (true) {
        $sock = socket_accept_($state->server);

        if (false === $sock) {
            panic('socket_accept: %s', socket_strerror(socket_last_error()));
        }

        $state->clients_add($sock);

        go(client_worker(...), $sock, $state);
    }
}, $state);

go(function (): void {
    $tick = 0;
    while (true) { // @phpstan-ignore while.alwaysTrue
        delay(Duration::milliseconds(1000));
        dprintfn('%s', ($tick = 1 - $tick) ? 'tick' : 'tock');
    }
});

// the loop will implicitly start here
