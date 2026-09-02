<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Öneri modelini (Python ALS) arka planda eğitir ve ardından
 * context dosyalarını yeniden üretir. Ağır iş olduğu için
 * RabbitMQ kuyruğunda çalışır — komutu çağıran kişi/cron
 * beklemek zorunda kalmaz.
 */
class TrainRecommenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Model eğitimi uzun sürebilir — timeout'u 30 dakikaya ayarlıyoruz.
     */
    public int $timeout = 1800;

    /**
     * Eğitim sırasında hata olursa en fazla 2 kez daha dene.
     */
    public int $tries = 3;

    public function handle(): void
    {
        Log::info('🧠 [Job] Öneri modeli eğitimi başlatılıyor...');

        $process = Process::fromShellCommandline(sprintf(
            'OPENBLAS_NUM_THREADS=1 %s train.py',
            escapeshellarg(config('services.recommender.python'))
        ));

        $process->setWorkingDirectory(base_path('recommender'));
        $process->setTimeout(null);

        $process->run(function ($type, $buffer) {
            Log::info('[TrainRecommenderJob] ' . trim($buffer));
        });

        if (!$process->isSuccessful()) {
            Log::error('❌ [Job] Eğitim başarısız: ' . $process->getErrorOutput());
            $this->fail(new \RuntimeException('Python eğitim scripti başarısız oldu.'));
            return;
        }

        Log::info('✅ [Job] Öneri modeli başarıyla eğitildi.');

        // Eğitim bittikten sonra context dosyalarını güncelle — tıpkı
        // TrainRecommender komutunun yaptığı gibi.
        \Illuminate\Support\Facades\Artisan::call('context:generate');

        Log::info('✅ [Job] Context dosyaları güncellendi.');
    }
}
