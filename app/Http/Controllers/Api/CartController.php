<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\CartRecommendationsRequest;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\ShowCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Services\UserContextGenerator;

class CartController extends Controller
{
    public function __construct(
        private UserContextGenerator $contextGenerator,
        private OrderService $orderService,
    ) {}

    public function show(ShowCartRequest $request)
    {
        $data = $request->validated();

        $cart = Cart::firstOrCreate($data);
        $cart->load('items.product.category');

        return response()->json(CartResource::make($cart));
    }

    /**
     * Sepet ekranındaki "Sana Özel Öneriler" paneli. DB'ye canlı sorgu
     * atmaz — sadece o kullanıcı+mağaza için önceden üretilmiş context
     * dosyasını okuyup "Önerilen Ürünler" bölümünü ayrıştırır.
     */
    public function recommendations(CartRecommendationsRequest $request)
    {
        $data = $request->validated();

        $user = User::findOrFail($data['user_id']);
        $store = Store::findOrFail($data['store_id']);

        return response()->json([
            'data' => $this->contextGenerator->parseRecommendations($user, $store),
        ]);
    }

    public function addItem(AddCartItemRequest $request)
    {
        $data = $request->validated();

        $product = Product::findOrFail($data['product_id']);

        // Sepet mağaza bazlı olduğu için, sadece o mağazanın kendi
        // ürünleri o sepete eklenebilir — başka mağazanın ürünü karışamaz.
        if ($product->store_id !== (int) $data['store_id']) {
            return response()->json([
                'message' => 'Bu ürün bu mağazaya ait değil.',
            ], 422);
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $data['user_id'],
            'store_id' => $data['store_id'],
        ]);

        $quantity = $data['quantity'] ?? 1;
        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->increment('quantity', $quantity);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        $cart->load('items.product.category');

        return response()->json(CartResource::make($cart));
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem)
    {
        $cartItem->update($request->validated());

        $cart = $cartItem->cart->load('items.product.category');

        return response()->json(CartResource::make($cart));
    }

    public function removeItem(CartItem $cartItem)
    {
        $cart = $cartItem->cart;
        $cartItem->delete();
        $cart->load('items.product.category');

        return response()->json(CartResource::make($cart));
    }

    /**
     * Sepeti gerçek bir siparişe (Order + OrderItem) çevirir ve sepeti
     * boşaltır. Asıl iş mantığı (transaction, toplam hesaplama, context
     * güncelleme) artık OrderService'te — controller sadece isteği
     * doğrulayıp servisi çağırıyor.
     */
    public function checkout(CheckoutRequest $request)
    {
        $data = $request->validated();

        $order = $this->orderService->checkout($data['user_id'], $data['store_id']);

        if (! $order) {
            return response()->json(['message' => 'Sepetiniz boş.'], 422);
        }

        return response()->json([
            'message' => 'Siparişiniz oluşturuldu.',
            'order_id' => $order->id,
            'total_amount' => $order->total_amount,
        ]);
    }
}
