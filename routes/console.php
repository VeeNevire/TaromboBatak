<?php

use App\Services\MargaNewsAutomationRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(MargaNewsAutomationRunner::class)->runIfDue())
    ->everyMinute()
    ->name('marga-news-hermes-automation')
    ->withoutOverlapping(15);
