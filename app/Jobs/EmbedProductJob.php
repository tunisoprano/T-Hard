<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tek bir ürün için Ollama embedding'i üretir ve veritabanına yazar.
 * Her ürün ayrı bir kuyruk mesajı olarak RabbitMQ'ya girer — böylece
 * birden fazla worker paralel çalışabilir.
 */
class EmbedProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Ollama'nın embedding üretmesi bazen yavaş olabilir.
     */
    public int $timeout = 120;

    /**
     * Ollama sunucusu geçici olarak meşgulse 3 kez dene.
     */
    public int $tries = 3;

    /**
     * Yeniden denemeler arasında 10 saniye bekle.
     */
    public int $backoff = 10;

    public function __construct(
        public int $productId,
    ) {}

    public function handle(OllamaService $ollama): void
    {
        $product = Product::with('category')->find($this->productId);

        if (!$product) {
            Log::warning("[EmbedProductJob] Ürün bulunamadı: #{$this->productId}");
            return;
        }

        // Sadece isim + kategoriyi embed ediyoruz. `description` alanı
        // şu an Faker'ın ürettiği anlamsız Lorem Ipsum metni — embed'e
        // dahil edilirse gerçek sinyali (isim+kategori) gürültüye boğup
        // arama kalitesini ciddi şekilde düşürüyor (test ettik).
        $text = sprintf('%s. Kategori: %s.', $product->name, $product->category->name);

        $product->update(['embedding' => $ollama->embed($text)]);

        Log::info("[EmbedProductJob] Embedding üretildi: {$product->name} (#{$product->id})");
    }
}
