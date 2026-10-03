<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
| Dijalankan lewat `php artisan schedule:work` di entrypoint container
| (TANPA cron host) — meniru pola deploy proyek monitoring yang sudah ada.
|
| - monitor:poll   → tiap POLL_INTERVAL detik (default 30, keputusan user)
| - metrics:prune  → tiap hari 03:00 (retensi METRICS_RETENTION_DAYS)
|
| CATATAN: `cron-expression` v3 TIDAK mendukung 6 field (detik), jadi interval
| sub-menit memakai API `everyXSeconds()` Laravel (repeatEvery internal).
| Nilai POLL_INTERVAL di-snap ke kelipatan yang didukung: 1/2/5/10/15/20/30/60.
*/

$interval = max(1, (int) config('noc.poll_interval', 30));

$event = Schedule::command('monitor:poll')->withoutOverlapping(2);

match (true) {
    $interval <= 1 => $event->everySecond(),
    $interval <= 2 => $event->everyTwoSeconds(),
    $interval <= 5 => $event->everyFiveSeconds(),
    $interval <= 10 => $event->everyTenSeconds(),
    $interval <= 15 => $event->everyFifteenSeconds(),
    $interval <= 20 => $event->everyTwentySeconds(),
    $interval <= 30 => $event->everyThirtySeconds(),
    default => $event->everyMinute(),
};

Schedule::command('metrics:prune')
    ->dailyAt('03:00')
    ->onOneServer();
