<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;
use Neumb\Scheduler\Scheduler;

use function Neumb\Scheduler\dprintfn;

$scheduler = Scheduler::get();

$scheduler->defer(Duration::milliseconds(200), static function (Duration $start, Duration $now): void {
    dprintfn('the deferred task 01 has executed');
});

$scheduler->defer(Duration::milliseconds(100), static function (Duration $start, Duration $now): void {
    dprintfn('the deferred task 02 has executed');
});

$scheduler->run(); // explicitly run the loop

/*
 * output:
 * [0100]: the deferred task 02 has executed
 * [0200]: the deferred task 01 has executed
 */
