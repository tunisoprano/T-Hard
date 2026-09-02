<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductsRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\StoreService;

class StoreController extends Controller
{
    public function __construct(private StoreService $storeService) {}

    public function index()
    {
        return StoreResource::collection(Store::all());
    }

    public function products(Store $store, StoreProductsRequest $request)
    {
        $data = $request->validated();

        $products = $this->storeService->productsFor($store, $data['category'] ?? null);

        return ProductResource::collection($products);
    }
}
