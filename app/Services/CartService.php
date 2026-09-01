<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use InvalidArgumentException;

class CartService
{
    public function getCart(int $userId, int $storeId): Cart
    {
        $cart = Cart::firstOrCreate([
            'user_id' => $userId,
            'store_id' => $storeId,
        ]);

        return $cart->load('items.product.category');
    }

    public function addItem(int $userId, int $storeId, int $productId, int $quantity = 1): Cart
    {
        $product = Product::findOrFail($productId);

        if ($product->store_id !== $storeId) {
            throw new InvalidArgumentException('Bu ürün bu mağazaya ait değil.');
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $userId,
            'store_id' => $storeId,
        ]);

        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->increment('quantity', $quantity);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        return $cart->load('items.product.category');
    }

    public function updateItem(CartItem $cartItem, int $quantity): Cart
    {
        $cartItem->update(['quantity' => $quantity]);

        return $cartItem->cart->load('items.product.category');
    }

    public function removeItem(CartItem $cartItem): Cart
    {
        $cart = $cartItem->cart;
        $cartItem->delete();

        return $cart->load('items.product.category');
    }
}
