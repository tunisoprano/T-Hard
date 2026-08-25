<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TrainRecommender extends Command
{
    /**
     * Komutun terminalde nasıl çağrılacağı.
     *
     * @var string
     */
    protected $signature = 'recommender:train';

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
        $this->info('🧠 Öneri modeli eğitimi başlatılıyor...');
        $this->line('Çalışma dizini: ' . base_path('recommender'));

        // Symfony Process ile komutu tanımlıyoruz. 
        // Not: Kendi venv'i içindeki python'ı kullanıyoruz.
        $process = Process::fromShellCommandline(
            'OPENBLAS_NUM_THREADS=1 venv/bin/python train.py'
        );

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
        return Command::SUCCESS;
    }
}
