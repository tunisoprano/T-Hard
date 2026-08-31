<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryProductsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(Category::all());
    }

    public function products(Category $category, CategoryProductsRequest $request)
    {
        $data = $request->validated();

        $products = $category->products()
            ->with(['category', 'store'])
            ->when($data['store_id'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->get();

        return ProductResource::collection($products);
    }
}
