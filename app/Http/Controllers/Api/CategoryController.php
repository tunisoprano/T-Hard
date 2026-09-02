<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryProductsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Services\CategoryService;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService) {}

    public function index()
    {
        return CategoryResource::collection(Category::all());
    }

    public function products(Category $category, CategoryProductsRequest $request)
    {
        $data = $request->validated();

        $products = $this->categoryService->productsFor($category, $data['store_id'] ?? null);

        return ProductResource::collection($products);
    }
}
