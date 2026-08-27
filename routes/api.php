<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\UserOrderController;
use Illuminate\Support\Facades\Route;

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category:slug}/products', [CategoryController::class, 'products']);

Route::get('stores', [StoreController::class, 'index']);
Route::get('stores/{store}/products', [StoreController::class, 'products']);

Route::get('products/{product}', [ProductController::class, 'show']);

Route::get('users/{user}/recommendations', [RecommendationController::class, 'index']);
Route::get('users/{user}/orders', [UserOrderController::class, 'index']);

Route::post('chat', [ChatController::class, 'respond']);
