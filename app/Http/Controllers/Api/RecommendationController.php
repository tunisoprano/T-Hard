<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecommendationResource;
use App\Models\User;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(User $user, Request $request)
    {
        $recommendations = $user->recommendations()
            ->with(['product.category', 'product.store'])
            ->when($request->query('store_id'), fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->orderBy('store_id')
            ->orderBy('rank')
            ->get();

        return RecommendationResource::collection($recommendations);
    }
}
