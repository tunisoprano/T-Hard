<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Sepeti gerçek bir siparişe (Order + OrderItem) çevirme iş mantığı
 * (eskiden CartController::checkout içindeydi). Transaction yönetimi,
 * toplam hesaplama ve context dosyası güncellemesi burada toplanıyor —
 * controller sadece isteği doğrulayıp bu akışı tetikliyor.
 */
class OrderService
{
    public function __construct(private UserContextGenerator $contextGenerator) {}

    /**
     * @return Order|null Sepet yoksa veya boşsa null döner.
     */
    public function checkout(int $userId, int $storeId): ?Order
    {
        $cart = Cart::where(['user_id' => $userId, 'store_id' => $storeId])
            ->with('items.product')
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return null;
        }

        $order = DB::transaction(function () use ($cart, $userId, $storeId) {
            $total = $cart->items->sum(fn (CartItem $item) => $item->quantity * $item->product->price);

            $order = Order::create([
                'user_id' => $userId,
                'store_id' => $storeId,
                'order_date' => now(),
                'total_amount' => $total,
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->price,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        return $order;
    }
}
