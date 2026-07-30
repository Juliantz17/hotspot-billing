<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// We are using absolute real-world expiration via cron now, not limit-uptime
Schedule::command('hotspot:clean-expired')->everyMinute();
Schedule::command('hotspot:clean-pending')->everyMinute();
Schedule::command('hotspot:enforce-fup')->everyMinute()->withoutOverlapping();
