<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kullanıcının önerilerini (istersen tek bir mağazayla sınırlayarak,
 * mağaza + sıra numarasına göre sıralı) getirme mantığı (eskiden
 * RecommendationController::index içindeydi).
 */
class RecommendationService
{
    public function forUser(User $user, ?int $storeId): Collection
    {
        return $user->recommendations()
            ->with(['product.category', 'product.store'])
            ->when($storeId, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->orderBy('store_id')
            ->orderBy('rank')
            ->get();
    }
}
