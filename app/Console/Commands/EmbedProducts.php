<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\OllamaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:embed')]
#[Description('Tüm ürünler için Ollama ile embedding üretir ve veritabanına kaydeder (semantik arama için).')]
class EmbedProducts extends Command
{
    public function handle(OllamaService $ollama): int
    {
        $products = Product::with('category')->get();
        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product) {
            // Sadece isim + kategoriyi embed ediyoruz. `description` alanı
            // şu an Faker'ın ürettiği anlamsız Lorem Ipsum metni — embed'e
            // dahil edilirse gerçek sinyali (isim+kategori) gürültüye boğup
            // arama kalitesini ciddi şekilde düşürüyor (test ettik).
            $text = sprintf('%s. Kategori: %s.', $product->name, $product->category->name);

            $product->update(['embedding' => $ollama->embed($text)]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$products->count()} ürün için embedding üretildi.");

        return self::SUCCESS;
    }
}
