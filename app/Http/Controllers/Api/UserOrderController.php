<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\User;

class UserOrderController extends Controller
{
    /**
     * Kullanıcının TÜM mağazalardaki geçmiş siparişlerini, mağazaya göre
     * gruplanmış şekilde döner. Chatbot'un aksine burada mağaza sınırı
     * yok — bu endpoint'in amacı demo/inceleme sırasında "gerçekte veri
     * ne diyor" sorusuna doğrudan cevap vermek.
     */
    public function index(User $user)
    {
        $items = OrderItem::whereIn('order_id', $user->orders()->select('id'))
            ->with(['product.category', 'order.store'])
            ->get();

        $byStore = $items->groupBy(fn ($item) => $item->order->store->id)
            ->map(function ($storeItems) {
                $store = $storeItems->first()->order->store;

                $products = $storeItems
                    ->groupBy(fn ($item) => $item->product_id)
                    ->map(function ($group) {
                        $product = $group->first()->product;

                        return [
                            'name' => $product->name,
                            'category' => $product->category->name,
                            'quantity' => $group->sum('quantity'),
                        ];
                    })
                    ->values();

                return [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'products' => $products,
                ];
            })
            ->values();

        return response()->json(['data' => $byStore]);
    }
}
