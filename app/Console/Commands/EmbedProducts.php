<?php

namespace App\Console\Commands;

use App\Jobs\EmbedProductJob;
use App\Models\Product;
use App\Services\OllamaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:embed {--sync : Kuyruğa atmadan doğrudan çalıştır}')]
#[Description('Tüm ürünler için Ollama ile embedding üretir ve veritabanına kaydeder (semantik arama için).')]
class EmbedProducts extends Command
{
    public function handle(OllamaService $ollama): int
    {
        $isSync = $this->option('sync');
        $totalCount = Product::count();
        $bar = null;

        if ($isSync) {
            $bar = $this->output->createProgressBar($totalCount);
        }

        // chunk() kullanarak ürünleri 100'er 100'er (bellek dostu) çekip işliyoruz.
        Product::with('category')->chunk(100, function ($products) use ($isSync, $ollama, $bar) {
            foreach ($products as $product) {
                if ($isSync) {
                    // Sadece isim + kategoriyi embed ediyoruz. `description` alanı
                    // şu an Faker'ın ürettiği anlamsız Lorem Ipsum metni — embed'e
                    // dahil edilirse gerçek sinyali (isim+kategori) gürültüye boğup
                    // arama kalitesini ciddi şekilde düşürüyor (test ettik).
                    $text = sprintf('%s. Kategori: %s.', $product->name, $product->category->name);
                    $product->update(['embedding' => $ollama->embed($text)]);
                    $bar?->advance();
                } else {
                    EmbedProductJob::dispatch($product->id);
                }
            }
        });

        if ($isSync) {
            $bar?->finish();
            $this->newLine();
            $this->info("{$totalCount} ürün için embedding üretildi.");
        } else {
            $this->info("🐰 {$totalCount} ürün için embedding işleri RabbitMQ kuyruğuna gönderildi.");
        }

        return self::SUCCESS;
    }
}
