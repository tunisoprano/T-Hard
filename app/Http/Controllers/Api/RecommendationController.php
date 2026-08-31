<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRecommendationsRequest;
use App\Http\Resources\RecommendationResource;
use App\Models\User;

class RecommendationController extends Controller
{
    public function index(User $user, UserRecommendationsRequest $request)
    {
        $data = $request->validated();

        $recommendations = $user->recommendations()
            ->with(['product.category', 'product.store'])
            ->when($data['store_id'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->orderBy('store_id')
            ->orderBy('rank')
            ->get();

        return RecommendationResource::collection($recommendations);
    }
}
