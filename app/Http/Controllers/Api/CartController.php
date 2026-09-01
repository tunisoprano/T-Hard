<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\CartRecommendationsRequest;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\ShowCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\Store;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\UserContextGenerator;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(
        private UserContextGenerator $contextGenerator,
        private OrderService $orderService,
        private CartService $cartService,
    ) {}

    public function show(ShowCartRequest $request)
    {
        $data = $request->validated();
        
        $cart = $this->cartService->getCart($data['user_id'], $data['store_id']);

        return response()->json(CartResource::make($cart));
    }

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

        try {
            $cart = $this->cartService->addItem(
                $data['user_id'],
                $data['store_id'],
                $data['product_id'],
                $data['quantity'] ?? 1
            );

            return response()->json(CartResource::make($cart));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem)
    {
        $data = $request->validated();
        
        $cart = $this->cartService->updateItem($cartItem, $data['quantity']);

        return response()->json(CartResource::make($cart));
    }

    public function removeItem(CartItem $cartItem)
    {
        $cart = $this->cartService->removeItem($cartItem);

        return response()->json(CartResource::make($cart));
    }

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
