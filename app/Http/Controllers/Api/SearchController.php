<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\OllamaService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const TOP_N = 10;

    public function index(Request $request, OllamaService $ollama)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        $queryVector = $ollama->embed($data['q']);

        $results = Product::with(['category', 'store'])
            ->whereNotNull('embedding')
            ->when($data['store_id'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->get()
            ->map(fn (Product $product) => [
                'product' => $product,
                'score' => $this->cosineSimilarity($queryVector, $product->embedding),
            ])
            ->sortByDesc('score')
            ->take(self::TOP_N)
            ->values();

        return response()->json([
            'data' => $results->map(fn ($r) => [
                'id' => $r['product']->id,
                'name' => $r['product']->name,
                'description' => $r['product']->description,
                'price' => $r['product']->price,
                'category' => $r['product']->category->name,
                'store' => $r['product']->store->name,
                'score' => round($r['score'], 4),
            ]),
        ]);
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
