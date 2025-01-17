<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Channel;

use function Neumb\Scheduler\chan;
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
    /** @var array<int,Socket> */
    private array $clients = [];

    /**
     * @var Channel<array{WeakReference<Socket>,string}>
     */
    private Channel $chan;

    public function __construct(
        public readonly Socket $server,
    ) {
        /** @var Channel<array{WeakReference<Socket>,string}> */
        $chan = chan();
        $this->chan = $chan;
    }

    /**
     * @return Channel<array{WeakReference<Socket>,string}>
     */
    public function chan_get(): Channel
    {
        return $this->chan;
    }

    public function clients_add(Socket $sock): void
    {
        $this->clients[(int) socket_export_stream($sock)] = $sock;
    }

    public function clients_del(Socket $sock): void
    {
        $stream = socket_export_stream($sock);

        if (! isset($this->clients[(int) $stream])) {
            throw new RuntimeException('The given socket is not set');
        }

        unset($this->clients[(int) $stream]);
    }

    public function clients_count(): int
    {
        return count($this->clients);
    }

    /**
     * @return iterable<Socket>
     */
    public function clients_iter(): iterable
    {
        yield from $this->clients;
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
    dprintfn('total connections: %d', $state->clients_count());

    $stream = socket_export_stream($socket);
    assert(is_resource($stream));

    $chan = $state->chan_get();

    while (true) {
        dprintfn('[client]: read');
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

        $chan->send([WeakReference::create($socket), $data]);
    }
}

go(function (State $state): void {
    while (true) {
        dprintfn('[server]');
        $sock = socket_accept_($state->server);

        if (false === $sock) {
            panic('socket_accept: %s', socket_strerror(socket_last_error()));
        }

        $state->clients_add($sock);

        go(client_worker(...), $sock, $state);
    }
}, $state);

go(function (State $state): void {
    $chan = $state->chan_get();

    while (! $chan->isClosed()) {
        [$ref, $data] = $chan->receive();

        foreach ($state->clients_iter() as $sock) {
            $stream = socket_export_stream($sock);
            assert(is_resource($stream));

            $name = ($sock === $ref->get())
                ? 'me'
                : sprintf('%02d', (int) $stream);

            stream_write($stream, sprintf('%s: %s', $name, $data));
        }
    }
}, $state);

// the loop will implicitly start here
