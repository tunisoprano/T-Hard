<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bir mağazaya ait ürünleri (istersen kategori slug'ıyla filtreleyerek)
 * getirme mantığı (eskiden StoreController::products içindeydi).
 */
class StoreService
{
    public function productsFor(Store $store, ?string $categorySlug): Collection
    {
        return $store->products()
            ->with(['category', 'store'])
            ->when($categorySlug, fn ($query, $slug) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $slug)
            ))
            ->get();
    }
}
