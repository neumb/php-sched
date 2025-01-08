<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;

use function Neumb\Scheduler\delay;
use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\go;

go(static function (string $id): void {
    for ($i = 0; $i < 2; ++$i) {
        delay(Duration::milliseconds(500));
        dprintfn('worker %s has woken up', $id);
    }

    dprintfn('worker %s has terminated', $id);
}, '01');

go(static function (string $id): void {
    for ($i = 0; $i < 5; ++$i) {
        delay(Duration::milliseconds(200));
        dprintfn('worker %s has woken up', $id);
    }

    dprintfn('worker %s has terminated', $id);
}, '02');

go(static function (string $id): void {
    for ($i = 0; $i < 3; ++$i) {
        delay(Duration::milliseconds(200));
        dprintfn('worker %s has woken up', $id);
    }

    dprintfn('worker %s has terminated', $id);
}, '03');

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
