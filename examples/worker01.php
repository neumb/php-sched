<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;

use function Neumb\Scheduler\delay;
use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\go;
use function Neumb\Scheduler\chan;
use Neumb\Scheduler\Channel;

/** @var Channel<string> */
$chan = chan();

go(static function (string $id, Channel $chan): void {
    /** @var Channel<string> $chan */

    for ($i = 0; $i < 2; ++$i) {
        delay(Duration::milliseconds(500));
        dprintfn('worker %s has woken up', $id);
        $chan->send($id);
    }

    dprintfn('worker %s has terminated', $id);
}, '01', $chan);

go(static function (string $id, Channel $chan): void {
    /** @var Channel<string> $chan */

    for ($i = 0; $i < 5; ++$i) {
        delay(Duration::milliseconds(200));
        dprintfn('worker %s has woken up', $id);
        $chan->send($id);
    }

    $chan->close();

    dprintfn('worker %s has terminated', $id);
}, '02', $chan);

go(static function (string $id, Channel $chan): void {
    /** @var Channel<string> $chan */

    while (! $chan->isClosed()) {
        $data = $chan->receive();
        dprintfn("worker %s has read from channel: [%s]", $id, $data);
    }

    dprintfn('worker %s has terminated', $id);
}, '03', $chan);

/*
 * output:
 * [0200]: worker 02 has woken up
 * [0201]: worker 03 has woken up
 * [0401]: worker 02 has woken up
 * [0401]: worker 03 has woken up
 * [0500]: worker 01 has woken up
 * [0601]: worker 02 has woken up
 * [0602]: worker 03 has woken up
 * [0602]: worker 03 has terminated
 * [0802]: worker 02 has woken up
 * [1001]: worker 01 has woken up
 * [1001]: worker 01 has terminated
 * [1002]: worker 02 has woken up
 * [1002]: worker 02 has terminated
 */
