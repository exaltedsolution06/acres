<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// cPanel needs only one cron entry: * * * * * php /path/artisan schedule:run
Schedule::command('marketplace:expire-subscriptions')->hourly();
Schedule::command('marketplace:expire-listings')->hourly();
Schedule::command('marketplace:send-search-alerts')->dailyAt('08:00')->timezone(config('marketplace.timezone'));
Schedule::command('marketplace:send-followups')->everyFifteenMinutes();
Schedule::command('marketplace:send-subscription-reminders')->dailyAt('09:00')->timezone(config('marketplace.timezone'));
Schedule::command('marketplace:maintain-verifications')->dailyAt('03:00')->timezone(config('marketplace.timezone'));
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
