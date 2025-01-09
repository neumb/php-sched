<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;
use Neumb\Scheduler\Runtime;

use function Neumb\Scheduler\dprintfn;

$runtime = Runtime::get();

$runtime->get()->repeat(Duration::milliseconds(500), static function (): bool {
    static $times = 0;
    assert(is_int($times));

    dprintfn('the recurrent task has executed %d times', ++$times);

    if ($times > 4) {
        dprintfn('the recurrent task has stopped');

        return false;
    }

    return true;
});

$runtime->run(); // explicitly run the loop

/*
 * output:
 * [0500]: the recurrent task has executed 1 times
 * [1000]: the recurrent task has executed 2 times
 * [1500]: the recurrent task has executed 3 times
 * [2000]: the recurrent task has executed 4 times
 * [2501]: the recurrent task has executed 5 times
 * [2501]: the recurrent task has stopped
 */
