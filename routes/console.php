<?php

use App\Console\Commands\MonitorServicesCommand;
use App\Console\Commands\PruneServiceDowntimeHistoryCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(MonitorServicesCommand::class)
    ->everyThirtySeconds()
    ->withoutOverlapping(1)
    ->runInBackground();

Schedule::command(PruneServiceDowntimeHistoryCommand::class)
    ->daily()
    ->withoutOverlapping(10)
    ->runInBackground();
