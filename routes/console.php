<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Her gece saat 03:00'te öneri modelini otomatik yeniden eğit
Schedule::command('recommender:train')->dailyAt('03:00');
