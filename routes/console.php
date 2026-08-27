<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Her gece saat 03:00'te öneri modelini yeniden eğit; bu komut kendi
// içinde başarılı olunca context:generate'i de tetikliyor (bkz.
// TrainRecommender::handle) — böylece context dosyaları her zaman
// güncel önerilerle üretiliyor, ayrı bir zamanlamaya gerek yok.
Schedule::command('recommender:train')->dailyAt('03:00');
