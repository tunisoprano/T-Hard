<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recommendation;
use App\Models\Store;
use App\Models\User;
use App\Services\UserContextGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserContextGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_generates_markdown_file_with_purchase_history_and_recommendations(): void
    {
        $store = Store::factory()->create(['name' => 'T-Hard Market']);
        $category = Category::factory()->create(['name' => 'Spor & Outdoor']);
        $user = User::factory()->create(['name' => 'Test Kullanıcı', 'store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Koşu Ayakkabısı', 'price' => 100]);

        $order = Order::factory()->create(['user_id' => $user->id, 'store_id' => $store->id, 'total_amount' => 300]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 3]);

        Recommendation::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'score' => 0.9,
            'rank' => 1,
        ]);

        $generator = app(UserContextGenerator::class);
        $markdown = $generator->generate($user, $store);

        $this->assertStringContainsString('Test Kullanıcı', $markdown);
        $this->assertStringContainsString('Koşu Ayakkabısı', $markdown);
        $this->assertStringContainsString('Spor & Outdoor', $markdown);
        $this->assertStringContainsString('toplam 3 adet', $markdown);

        Storage::disk('local')->assertExists($generator->path($user, $store));
    }

    public function test_users_without_orders_get_a_file_saying_so_instead_of_being_skipped(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $generator = app(UserContextGenerator::class);
        $markdown = $generator->generate($user, $store);

        $this->assertStringContainsString('henüz siparişi yok', $markdown);
    }

    public function test_read_returns_null_when_file_does_not_exist_yet(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();

        $generator = app(UserContextGenerator::class);

        $this->assertNull($generator->read($user, $store));
    }

    public function test_regenerate_master_combines_all_store_files_into_one(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $storeA->id]);

        $generator = app(UserContextGenerator::class);
        $generator->generate($user, $storeA);
        $generator->generate($user, $storeB);

        $generator->regenerateMaster($user);
        $master = $generator->readMaster($user);

        Storage::disk('local')->assertExists($generator->masterPath($user));
        $this->assertStringContainsString($storeA->name, $master);
        $this->assertStringContainsString($storeB->name, $master);
    }

    public function test_read_master_returns_null_when_never_generated(): void
    {
        $user = User::factory()->create();

        $generator = app(UserContextGenerator::class);

        $this->assertNull($generator->readMaster($user));
    }
}
