<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Content and social publishing.
Schedule::command('cms:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('social:dispatch-due')->everyMinute()->withoutOverlapping();
Schedule::command('social:refresh-tokens')->hourly()->withoutOverlapping();

// Queue metrics for the Horizon dashboard.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->environments('production');

// Housekeeping. Audit logs are append-only and are never pruned.
Schedule::command('model:prune')->dailyAt('01:00');
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->dailyAt('01:10');
Schedule::command('auth:clear-resets')->dailyAt('01:20');

// Backups: database and uploads, encrypted, kept per config/backup.php.
Schedule::command('backup:clean')->dailyAt('01:30')->environments('production')->onOneServer();
Schedule::command('backup:run')->dailyAt('02:00')->environments('production')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('07:00')->environments('production');
