<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductsRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Models\Store;

class StoreController extends Controller
{
    public function index()
    {
        return StoreResource::collection(Store::all());
    }

    public function products(Store $store, StoreProductsRequest $request)
    {
        $data = $request->validated();

        $products = $store->products()
            ->with(['category', 'store'])
            ->when($data['category'] ?? null, fn ($query, $slug) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $slug)
            ))
            ->get();

        return ProductResource::collection($products);
    }
}
