<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:tracking-aus-post')->everyFifteenMinutes()->appendOutputTo(storage_path('logs/tracking-aus-post.log'));
Schedule::command('app:notified-item-expired')->dailyAt('05:00')->appendOutputTo(storage_path('logs/notified-item-expired.log'));
// Schedule::command('app:notified-item-expired')->everyMinute()->appendOutputTo(storage_path('logs/notified-item-expired.log'));
Schedule::command('app:email-processed')->dailyAt('05:00')->appendOutputTo(storage_path('logs/email-processed.log'));
Schedule::command('app:generate-label')->everyMinute()->appendOutputTo(storage_path('logs/generate-label.log'));
Schedule::command('app:reset_-get-items')->dailyAt('00:00')->appendOutputTo(storage_path('logs/reset_getitems.log'));