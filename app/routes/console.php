<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('snipeit:sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('bookings:send-reminders')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('bookings:send-daily-summary')
    ->dailyAt('07:00')
    ->withoutOverlapping();

Schedule::command('audit:prune')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Self-gates on the "scheduled backups" setting — a no-op until an admin
// turns it on (and a passphrase is configured).
Schedule::command('kitloan:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();
