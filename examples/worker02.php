<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\go;
use function Neumb\Scheduler\sched;

go(static function (): void {
    for ($i = 0; $i < 3; ++$i) {
        dprintfn('tick');
        sched();
    }
});

go(static function (): void {
    for ($i = 0; $i < 3; ++$i) {
        dprintfn('tock');
        sched();
    }
});

go(static function (): void {
    for ($i = 0; $i < 3; ++$i) {
        dprintfn('click');
        sched();
    }
});

go(static function (): void {
    for ($i = 0; $i < 3; ++$i) {
        dprintfn('clack');
        sched();
    }
});

/*
 * output:
 * [0000]: tick
 * [0000]: tock
 * [0000]: click
 * [0000]: clack
 * [0000]: tick
 * [0000]: tock
 * [0000]: click
 * [0000]: clack
 * [0000]: tick
 * [0000]: tock
 * [0000]: click
 * [0000]: clack
 */
