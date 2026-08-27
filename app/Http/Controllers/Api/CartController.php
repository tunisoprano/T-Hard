<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\UserContextGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct(private UserContextGenerator $contextGenerator) {}

    public function show(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'store_id' => ['required', 'exists:stores,id'],
        ]);

        $cart = Cart::firstOrCreate($data);
        $cart->load('items.product.category');

        return response()->json($this->formatCart($cart));
    }

    /**
     * Sepet ekranındaki "Sana Özel Öneriler" paneli. DB'ye canlı sorgu
     * atmaz — sadece o kullanıcı+mağaza için önceden üretilmiş context
     * dosyasını okuyup "Önerilen Ürünler" bölümünü ayrıştırır.
     */
    public function recommendations(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'store_id' => ['required', 'exists:stores,id'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $store = Store::findOrFail($data['store_id']);

        return response()->json([
            'data' => $this->contextGenerator->parseRecommendations($user, $store),
        ]);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'store_id' => ['required', 'exists:stores,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

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

        return response()->json($this->formatCart($cart));
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cartItem->update($data);

        $cart = $cartItem->cart->load('items.product.category');

        return response()->json($this->formatCart($cart));
    }

    public function removeItem(CartItem $cartItem)
    {
        $cart = $cartItem->cart;
        $cartItem->delete();
        $cart->load('items.product.category');

        return response()->json($this->formatCart($cart));
    }

    /**
     * Sepeti gerçek bir siparişe (Order + OrderItem) çevirir ve sepeti
     * boşaltır.
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'store_id' => ['required', 'exists:stores,id'],
        ]);

        $cart = Cart::where($data)->with('items.product')->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Sepetiniz boş.'], 422);
        }

        $order = DB::transaction(function () use ($cart, $data) {
            $total = $cart->items->sum(fn (CartItem $item) => $item->quantity * $item->product->price);

            $order = Order::create([
                'user_id' => $data['user_id'],
                'store_id' => $data['store_id'],
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

        // Context dosyasını burada, transaction TAMAMEN bittikten (yani
        // sipariş kalemleri de yazıldıktan) sonra güncelliyoruz. Bunu bir
        // Order "created" model event'ine bağlamadık çünkü o event, Order
        // satırı oluşur oluşmaz (kalemler henüz eklenmeden) tetiklenir —
        // context dosyası siparişin içeriğini eksik/boş görürdü.
        $this->contextGenerator->generate($order->user, $order->store);

        return response()->json([
            'message' => 'Siparişiniz oluşturuldu.',
            'order_id' => $order->id,
            'total_amount' => $order->total_amount,
        ]);
    }

    private function formatCart(Cart $cart): array
    {
        $items = $cart->items->map(fn (CartItem $item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $item->product->name,
            'category' => $item->product->category->name,
            'price' => $item->product->price,
            'quantity' => $item->quantity,
            'subtotal' => round($item->quantity * $item->product->price, 2),
        ]);

        return [
            'cart_id' => $cart->id,
            'items' => $items,
            'total' => round($items->sum('subtotal'), 2),
        ];
    }
}
