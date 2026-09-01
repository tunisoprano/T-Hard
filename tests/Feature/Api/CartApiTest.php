<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\UserContextGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Checkout artık gerçek bir context dosyası yazıyor (bkz.
        // test_checkout_regenerates_the_users_context_file) — bunu
        // gerçek storage/app yerine sahte bir diske yönlendiriyoruz ki
        // testler dosya sistemini kirletmesin.
        Storage::fake('local');
    }

    public function test_adding_item_creates_cart_and_returns_totals(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'price' => 100]);

        $response = $this->postJson('/api/cart/items', [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.subtotal', 200)
            ->assertJsonPath('total', 200);

        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_adding_same_product_twice_increments_quantity_instead_of_duplicating(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'price' => 50]);

        $payload = [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
        ];

        $this->postJson('/api/cart/items', $payload);
        $response = $this->postJson('/api/cart/items', $payload);

        $response->assertJsonPath('items.0.quantity', 2);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_cannot_add_product_from_a_different_store(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $storeA->id]);
        $productFromStoreB = Product::factory()->create(['store_id' => $storeB->id, 'category_id' => $category->id]);

        $response = $this->postJson('/api/cart/items', [
            'user_id' => $user->id,
            'store_id' => $storeA->id,
            'product_id' => $productFromStoreB->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_can_update_item_quantity(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'price' => 10]);

        $cart = Cart::create(['user_id' => $user->id, 'store_id' => $store->id]);
        $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 4]);

        $response->assertOk()->assertJsonPath('items.0.quantity', 4);
    }

    public function test_can_remove_item(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id]);

        $cart = Cart::create(['user_id' => $user->id, 'store_id' => $store->id]);
        $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->deleteJson("/api/cart/items/{$item->id}");

        $response->assertOk()->assertJsonCount(0, 'items');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_converts_cart_into_a_real_order_and_empties_cart(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'price' => 75]);

        $cart = Cart::create(['user_id' => $user->id, 'store_id' => $store->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 3]);

        $response = $this->postJson('/api/cart/checkout', [
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $response->assertOk()->assertJsonPath('total_amount', 225);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'total_amount' => 225,
        ]);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_regenerates_the_users_context_file(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Test Ürünü', 'price' => 50]);

        $cart = Cart::create(['user_id' => $user->id, 'store_id' => $store->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->postJson('/api/cart/checkout', [
            'user_id' => $user->id,
            'store_id' => $store->id,
        ])->assertOk();

        $generator = app(UserContextGenerator::class);
        Storage::disk('local')->assertExists("user-contexts/{$store->id}/{$user->id}.md");

        $content = $generator->read($user, $store);
        $this->assertStringContainsString('Test Ürünü', $content);
        $this->assertStringContainsString('toplam 2 adet', $content);
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $response = $this->postJson('/api/cart/checkout', [
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cart_recommendations_are_parsed_from_context_file_not_db(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'price' => 199.99]);

        $generator = app(UserContextGenerator::class);
        $markdown = "## Bu Mağaza İçin Önerilen Ürünler\n\n"
            ."- Test Önerisi (Test Kategori, 199.99 TL) [#{$product->id}]";
        Storage::disk('local')->put("user-contexts/{$store->id}/{$user->id}.md", $markdown);

        $response = $this->getJson("/api/cart/recommendations?user_id={$user->id}&store_id={$store->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $product->id);
        $response->assertJsonPath('data.0.name', 'Test Önerisi');
        $response->assertJsonPath('data.0.category', 'Test Kategori');
    }

    public function test_cart_recommendations_empty_when_no_context_file_exists(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $response = $this->getJson("/api/cart/recommendations?user_id={$user->id}&store_id={$store->id}");

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}
