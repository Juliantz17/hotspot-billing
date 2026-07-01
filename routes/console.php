<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// The expired hotspot users cleanup is now handled automatically by MikroTik limit-uptime
// Schedule::command('hotspot:clean-expired')->everyMinute();
