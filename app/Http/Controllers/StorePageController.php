<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;

class StorePageController extends Controller
{
    public function show(Store $store)
    {
        $products = $store->products()->with('category')->get();

        return view('store', [
            'store' => $store,
            'productsByCategory' => $products->groupBy(fn ($product) => $product->category->name),
            'users' => User::select('id', 'name', 'persona')->orderBy('name')->get(),
            'otherStores' => Store::where('id', '!=', $store->id)->get(),
        ]);
    }
}
