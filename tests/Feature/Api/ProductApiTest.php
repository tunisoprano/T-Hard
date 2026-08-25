<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_show_single_product(): void
    {
        $store = Store::factory()->create(['name' => 'T-Hard Market']);
        $category = Category::factory()->create(['name' => 'Pantolon', 'slug' => 'pantolon']);
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Slim Fit Pantolon',
            'price' => 199.99,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Slim Fit Pantolon'])
            ->assertJsonFragment(['price' => 199.99]);
    }

    public function test_product_includes_category_and_store(): void
    {
        $store = Store::factory()->create(['name' => 'T-Hard Plus']);
        $category = Category::factory()->create(['name' => 'Gömlek', 'slug' => 'gomlek']);
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        // Ürün response'unda category ve store bilgisi de nested olarak gelmeli
        $response->assertStatus(200)
            ->assertJsonPath('data.category.name', 'Gömlek')
            ->assertJsonPath('data.store.name', 'T-Hard Plus');
    }

    public function test_returns_404_for_nonexistent_product(): void
    {
        $response = $this->getJson('/api/products/99999');

        $response->assertStatus(404);
    }
}
