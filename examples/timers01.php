<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Neumb\Scheduler\Duration;
use Neumb\Scheduler\Scheduler;

use function Neumb\Scheduler\dprintfn;

$scheduler = Scheduler::get();

$scheduler->defer(Duration::milliseconds(100), static function (int $start, int $now): void {
    dprintfn('the deferred task has executed');
});

$scheduler->defer(Duration::milliseconds(200), static function (int $start, int $now): void {
    dprintfn('the deferred task has executed');
});

$scheduler->run();
