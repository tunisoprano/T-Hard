<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUserOrdersRequest;
use App\Models\Order;
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

    /**
     * "Siparişlerim" ekranı için: her siparişi AYRI bir satır olarak
     * döner (sipariş no, tarih, ürünler, toplam) — index()'teki gibi
     * ürün bazında özetlemez. `store_id` verilirse sadece o mağazadaki
     * siparişler döner, verilmezse kullanıcının tüm mağazalardaki
     * siparişleri (en yeni önce).
     */
    public function detailed(ListUserOrdersRequest $request, User $user)
    {
        $data = $request->validated();

        $orders = $user->orders()
            ->with(['store', 'orderItems.product.category'])
            ->when($data['store_id'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->orderByDesc('order_date')
            ->get()
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'order_date' => $order->order_date->toIso8601String(),
                'store' => $order->store->name,
                'total_amount' => $order->total_amount,
                'items' => $order->orderItems->map(fn (OrderItem $item) => [
                    'name' => $item->product->name,
                    'category' => $item->product->category->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]),
            ]);

        return response()->json(['data' => $orders]);
    }
}
