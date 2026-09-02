<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kategoriye ait ürünleri (istersen tek bir mağazayla sınırlayarak)
 * getirme mantığı (eskiden CategoryController::products içindeydi).
 */
class CategoryService
{
    public function productsFor(Category $category, ?int $storeId): Collection
    {
        return $category->products()
            ->with(['category', 'store'])
            ->when($storeId, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->get();
    }
}
