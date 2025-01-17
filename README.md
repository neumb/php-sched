## Cooperative Scheduler in PHP
This project features an experimental cooperative scheduler implemented in PHP. Designed primarily for educational and recreational purposes, this scheduler allows to explore the concepts of cooperative multitasking within a PHP environment.

Unlike traditional preemptive scheduling, this cooperative scheduler requires tasks to yield control voluntarily and performs context switching in user space.
Under the hood, context swithing managed through the functions `go`, `stream_read`, `stream_write`, `socket_accept_`, `delay`.

Please note that this scheduler is not intended for production use. It is a proof-of-concept that demonstrates the principles of cooperative scheduling and should be used with caution in any real-world applications.

### Getting Started
```php
use Neumb\Scheduler\Duration;

use function Neumb\Scheduler\delay;
use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\go;
use function Neumb\Scheduler\chan;
use Neumb\Scheduler\Channel;

/** @var Channel<string> */
$chan = chan();

// The `go` function dispatches a routine to run in the background.
// It can accept a variable number of arguments for the routine.

go(static function (string $id, Channel $chan): void {
    /** @var Channel<string> $chan */

    for ($i = 0; $i < 2; ++$i) {
        delay(Duration::milliseconds(500)); // yield the processor, switches to another routine
        dprintfn('worker %s has woken up', $id);
        $chan->send($id);
    }

    $chan->close();

    dprintfn('worker %s has terminated', $id);
}, '01', $chan);

go(static function (string $id): void {
    for ($i = 0; $i < 5; ++$i) {
        delay(Duration::milliseconds(200)); // yield the processor
        dprintfn('worker %s has woken up', $id);
    }

    dprintfn('worker %s has terminated', $id);
}, '02');

go(static function (string $id, Channel $chan): void {
    /** @var Channel<string> $chan */

    while (! $chan->isClosed()) {
        $msg = $chan->receive();
        dprintfn("worker %s has received from channel: '%s'", $id, $msg);
    }

    dprintfn('worker %s has terminated', $id);
}, '03', $chan);

// This loop will be initiated to start executing the tasks.
// It will block the current execution until all pending tasks have been completed.
```
