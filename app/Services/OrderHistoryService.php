<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Kullanıcının sipariş geçmişini iki farklı şekilde özetleme mantığı
 * (eskiden UserOrderController içindeydi):
 *  - groupedByStore(): ürün bazında, mağazaya göre gruplanmış özet
 *  - detailedFor(): her siparişi ayrı bir satır olarak listeleyen döküm
 */
class OrderHistoryService
{
    /**
     * Kullanıcının TÜM mağazalardaki geçmiş siparişlerini, mağazaya göre
     * gruplanmış ve ürün bazında özetlenmiş şekilde döner. Chatbot'un
     * aksine burada mağaza sınırı yok — amaç demo/inceleme sırasında
     * "gerçekte veri ne diyor" sorusuna doğrudan cevap vermek.
     */
    public function groupedByStore(User $user): Collection
    {
        $items = OrderItem::whereIn('order_id', $user->orders()->select('id'))
            ->with(['product.category', 'order.store'])
            ->get();

        return $items->groupBy(fn ($item) => $item->order->store->id)
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
    }

    /**
     * "Siparişlerim" ekranı için: her siparişi AYRI bir satır olarak
     * döner (sipariş no, tarih, ürünler, toplam) — groupedByStore()'daki
     * gibi ürün bazında özetlemez. $storeId verilirse sadece o mağazadaki
     * siparişler döner, verilmezse kullanıcının tüm mağazalardaki
     * siparişleri (en yeni önce).
     */
    public function detailedFor(User $user, ?int $storeId): Collection
    {
        return $user->orders()
            ->with(['store', 'orderItems.product.category'])
            ->when($storeId, fn ($query, $storeId) => $query->where('store_id', $storeId))
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
    }
}
