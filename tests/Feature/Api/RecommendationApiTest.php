<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recommendation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_user_recommendations(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $category = Category::factory()->create();

        // 3 öneri oluştur
        for ($i = 1; $i <= 3; $i++) {
            $product = Product::factory()->create([
                'store_id' => $store->id,
                'category_id' => $category->id,
            ]);
            Recommendation::create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'product_id' => $product->id,
                'score' => 1.0 - ($i * 0.1),
                'rank' => $i,
            ]);
        }

        $response = $this->getJson("/api/users/{$user->id}/recommendations");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_recommendations_by_store_id(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store1->id]);
        $category = Category::factory()->create();

        // store1 için 2 öneri
        for ($i = 1; $i <= 2; $i++) {
            $product = Product::factory()->create([
                'store_id' => $store1->id,
                'category_id' => $category->id,
            ]);
            Recommendation::create([
                'user_id' => $user->id,
                'store_id' => $store1->id,
                'product_id' => $product->id,
                'score' => 0.9,
                'rank' => $i,
            ]);
        }

        // store2 için 1 öneri
        $product2 = Product::factory()->create([
            'store_id' => $store2->id,
            'category_id' => $category->id,
        ]);
        Recommendation::create([
            'user_id' => $user->id,
            'store_id' => $store2->id,
            'product_id' => $product2->id,
            'score' => 0.5,
            'rank' => 1,
        ]);

        // store1 filtresi ile sadece 2 sonuç gelmeli
        $response = $this->getJson("/api/users/{$user->id}/recommendations?store_id={$store1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_recommendations_are_ordered_by_store_and_rank(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        $category = Category::factory()->create();

        // rank=2'yi ÖNCE, rank=1'i SONRA oluştur (sıralama testi)
        $product1 = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'İkinci Ürün',
        ]);
        Recommendation::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'product_id' => $product1->id,
            'score' => 0.5,
            'rank' => 2,
        ]);

        $product2 = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Birinci Ürün',
        ]);
        Recommendation::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'product_id' => $product2->id,
            'score' => 0.9,
            'rank' => 1,
        ]);

        $response = $this->getJson("/api/users/{$user->id}/recommendations?store_id={$store->id}");

        // rank=1 olan "Birinci Ürün" ilk sırada gelmeli
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(1, $data[0]['rank']);
        $this->assertEquals(2, $data[1]['rank']);
    }

    public function test_returns_empty_for_user_with_no_recommendations(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $response = $this->getJson("/api/users/{$user->id}/recommendations");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
