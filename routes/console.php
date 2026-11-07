<?php

use Illuminate\Support\Facades\Schedule;

// Run `php artisan schedule:run` every minute from cron (or `php artisan schedule:work` locally).
Schedule::command('zairon:invoice-reminders')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('auth:clear-resets')->daily();
