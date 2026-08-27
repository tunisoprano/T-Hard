<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_detailed_orders_lists_each_order_separately(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'price' => 100]);

        $orderOne = Order::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'order_date' => now()->subDays(2),
            'total_amount' => 100,
        ]);
        OrderItem::factory()->create(['order_id' => $orderOne->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);

        $orderTwo = Order::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'order_date' => now(),
            'total_amount' => 200,
        ]);
        OrderItem::factory()->create(['order_id' => $orderTwo->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]);

        $response = $this->getJson("/api/users/{$user->id}/orders/detailed?store_id={$store->id}");

        $response->assertOk()->assertJsonCount(2, 'data');
        // en yeni siparis once gelmeli
        $response->assertJsonPath('data.0.order_id', $orderTwo->id);
        $response->assertJsonPath('data.1.order_id', $orderOne->id);
    }

    public function test_detailed_orders_filters_by_store(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $storeA->id]);
        $product = Product::factory()->create(['store_id' => $storeA->id, 'category_id' => $category->id]);

        $orderInA = Order::factory()->create(['user_id' => $user->id, 'store_id' => $storeA->id]);
        OrderItem::factory()->create(['order_id' => $orderInA->id, 'product_id' => $product->id]);

        $orderInB = Order::factory()->create(['user_id' => $user->id, 'store_id' => $storeB->id]);
        OrderItem::factory()->create(['order_id' => $orderInB->id]);

        $response = $this->getJson("/api/users/{$user->id}/orders/detailed?store_id={$storeA->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.order_id', $orderInA->id);
    }

    public function test_detailed_orders_without_store_filter_returns_all_stores(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $storeA->id]);

        $orderInA = Order::factory()->create(['user_id' => $user->id, 'store_id' => $storeA->id]);
        OrderItem::factory()->create(['order_id' => $orderInA->id]);

        $orderInB = Order::factory()->create(['user_id' => $user->id, 'store_id' => $storeB->id]);
        OrderItem::factory()->create(['order_id' => $orderInB->id]);

        $response = $this->getJson("/api/users/{$user->id}/orders/detailed");

        $response->assertOk()->assertJsonCount(2, 'data');
    }
}
