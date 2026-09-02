<?php

namespace App\Console\Commands;

use App\Jobs\TrainRecommenderJob;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TrainRecommender extends Command
{
    /**
     * Komutun terminalde nasıl çağrılacağı.
     * --sync flag'i: Job'a atmadan doğrudan (senkron) çalıştırır.
     *
     * @var string
     */
    protected $signature = 'recommender:train {--sync : Kuyruğa atmadan doğrudan çalıştır}';

    /**
     * php artisan list yazdığımızda görünecek açıklama.
     *
     * @var string
     */
    protected $description = 'Python (ALS) öneri motorunu çalıştırır ve recommendations tablosunu günceller.';

    /**
     * Komut çalıştırıldığında işletilecek kod.
     */
    public function handle()
    {
        if ($this->option('sync')) {
            return $this->runSync();
        }

        TrainRecommenderJob::dispatch();
        $this->info('🐰 Eğitim işi RabbitMQ kuyruğuna gönderildi. Worker arka planda çalıştıracak.');

        return Command::SUCCESS;
    }

    /**
     * Eski davranış: Python'u doğrudan çalıştır (test/debug için).
     */
    private function runSync(): int
    {
        $this->info('🧠 Öneri modeli eğitimi başlatılıyor (senkron)...');
        $this->line('Çalışma dizini: ' . base_path('recommender'));

        // Symfony Process ile komutu tanımlıyoruz.
        // Not: Python yolu config'ten geliyor — lokalde proje içindeki venv,
        // Docker'da /opt/venv (bkz. config/services.php).
        $process = Process::fromShellCommandline(sprintf(
            'OPENBLAS_NUM_THREADS=1 %s train.py',
            escapeshellarg(config('services.recommender.python'))
        ));

        // Komutun çalışacağı klasörü ayarlıyoruz
        $process->setWorkingDirectory(base_path('recommender'));

        // Veri büyüdükçe eğitim uzun sürebilir, timeout'u kaldırıyoruz
        $process->setTimeout(null);

        // Komutu çalıştır ve çıktıyı (Python'ın printlerini) anlık olarak ekrana bas
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        // Eğer komut hata verdiyse (exit code 0 değilse)
        if (!$process->isSuccessful()) {
            $this->error('❌ Eğitim sırasında bir hata oluştu!');
            $this->error($process->getErrorOutput());
            return Command::FAILURE;
        }

        $this->info("\n✅ Öneri modeli başarıyla eğitildi ve veritabanı güncellendi!");

        // Context dosyaları (recommendations tablosundaki "önerilen ürünler"
        // bölümünü içeriyor) her zaman eğitimden HEMEN SONRA üretilmeli —
        // yoksa chatbot/sepet ekranı bayat önerilerle çalışır. Bu komutu
        // burada çağırmak, hem cron'da hem elle çalıştırmada sırayı garanti
        // ediyor (ayrı ayrı zamanlanmış iki cron'un zamanlamasına güvenmek
        // yerine).
        $this->call('context:generate');

        return Command::SUCCESS;
    }
}
