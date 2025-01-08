<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;

use function Neumb\Scheduler\delay;
use function Neumb\Scheduler\dprintfn;
use function Neumb\Scheduler\go;

go(static function (string $id): void {
    for ($i = 0; $i < 5; ++$i) {
        delay(Duration::milliseconds(200));
        dprintfn('worker %s has woken up', $id);
    }
}, '01');

go(static function (string $id): void {
    for ($i = 0; $i < 5; ++$i) {
        delay(Duration::milliseconds(500));
        dprintfn('worker %s has woken up', $id);
    }
}, '02');

go(static function (string $id): void {
    for ($i = 0; $i < 5; ++$i) {
        delay(Duration::milliseconds(500));
        dprintfn('worker %s has woken up', $id);
    }
}, '03');
