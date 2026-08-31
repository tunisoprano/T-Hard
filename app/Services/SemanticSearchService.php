<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Bir metin sorgusunu bge-m3 ile embed edip, veritabanındaki ürün
 * embedding'leriyle kosinüs benzerliği üzerinden en yakın N ürünü döner.
 *
 * Neden ayrı bir servis: bu sadece bir matematik fonksiyonu değil,
 * "embed et -> ürünleri çek -> skorla -> sırala -> ilk N'i al" şeklinde
 * uçtan uca bir iş akışı (business logic) — controller'ın sorumluluğu
 * sadece HTTP isteğini doğrulayıp bu akışı tetiklemek olmalı.
 */
class SemanticSearchService
{
    private const TOP_N = 10;

    public function __construct(private OllamaService $ollama) {}

    public function search(string $query, ?int $storeId): Collection
    {
        $queryVector = $this->ollama->embed($query);

        return Product::with(['category', 'store'])
            ->whereNotNull('embedding')
            ->when($storeId, fn ($q, $id) => $q->where('store_id', $id))
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'category' => $product->category->name,
                'store' => $product->store->name,
                'score' => round($this->cosineSimilarity($queryVector, $product->embedding), 4),
            ])
            ->sortByDesc('score')
            ->take(self::TOP_N)
            ->values();
    }

    /**
     * İki vektör arasındaki "yön" benzerliğini -1 ile 1 arasında ölçer
     * (1 = aynı yön/anlam, 0 = alakasız, -1 = tam zıt). Vektörlerin
     * büyüklüğünü değil sadece yönünü karşılaştırdığı için, metin
     * uzunluğundan bağımsız, saf anlam benzerliği verir.
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dotProduct += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
