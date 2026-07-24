<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;
use App\Services\SiKas\QrisSyncService;
use App\Services\Social\PublishSchedulerService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule QRIS Sync every hour
Schedule::call(function () {
    app(QrisSyncService::class)->syncAll();
})->hourly();

// Schedule Social Media Publishing to run every minute
Schedule::call(function () {
    app(PublishSchedulerService::class)->processPendingJobs();
})->everyMinute();
