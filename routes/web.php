<?php

use App\Http\Controllers\ChatUiController;
use App\Http\Controllers\StorePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/chat', [ChatUiController::class, 'index']);

Route::get('/magaza/{store}', [StorePageController::class, 'show']);
