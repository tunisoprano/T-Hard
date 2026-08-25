<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        return StoreResource::collection(Store::all());
    }

    public function products(Store $store, Request $request)
    {
        $products = $store->products()
            ->with(['category', 'store'])
            ->when($request->query('category'), fn ($query, $slug) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $slug)
            ))
            ->get();

        return ProductResource::collection($products);
    }
}
