<?php

use App\Console\Commands\AutoLockExpiredRides;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-lock rides that exceeded package time + timeout buffer.
Schedule::command(AutoLockExpiredRides::class)->everyMinute();
