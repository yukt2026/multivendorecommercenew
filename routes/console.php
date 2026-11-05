<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:expire-reservations')->everyMinute();
Schedule::command('settlements:generate')->weeklyOn(1, '02:00');
Schedule::command('payments:reconcile')->dailyAt('03:00');
Schedule::command('sitemap:generate')->dailyAt('04:00');
Schedule::command('notifications:retry-failed')->hourly();
