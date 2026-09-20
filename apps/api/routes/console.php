<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires `php artisan schedule:work` (or a real cron entry calling
// `schedule:run` every minute) to actually fire — same caveat as Horizon
// needing `php artisan horizon` running: nothing in this sandbox keeps a
// long-lived process alive between sessions.
Schedule::command('reminders:dispatch-due')->everyMinute();
Schedule::command('accounts:purge-deleted')->daily();
