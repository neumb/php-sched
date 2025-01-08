<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;
use Neumb\Scheduler\Scheduler;

use function Neumb\Scheduler\dprintfn;

$scheduler = Scheduler::get();

$scheduler->repeat(Duration::milliseconds(500), static function (int $start, int $now): bool {
    static $times = 0;
    assert(is_int($times));

    dprintfn('the recurrent task has executed %d times', ++$times);

    if ($times > 4) {
        dprintfn('the recurrent task has stopped');

        return false;
    }

    return true;
});

$scheduler->run();
