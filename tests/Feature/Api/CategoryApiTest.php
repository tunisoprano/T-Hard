<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_all_categories(): void
    {
        Category::factory()->count(4)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_category_has_name_and_slug(): void
    {
        Category::factory()->create([
            'name' => 'Pantolon',
            'slug' => 'pantolon',
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Pantolon',
                'slug' => 'pantolon',
            ]);
    }

    public function test_can_list_products_by_category_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'pantolon']);
        $store = Store::factory()->create();

        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'store_id' => $store->id,
        ]);

        $response = $this->getJson('/api/categories/pantolon/products');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_category_products_by_store_id(): void
    {
        $category = Category::factory()->create(['slug' => 'gomlek']);
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();

        // store1'e 2 gömlek, store2'ye 1 gömlek ekle
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'store_id' => $store1->id,
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'store_id' => $store2->id,
        ]);

        // store1'in gömlekleri filtrele
        $response = $this->getJson("/api/categories/gomlek/products?store_id={$store1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_404_for_nonexistent_category_slug(): void
    {
        $response = $this->getJson('/api/categories/olmayan-kategori/products');

        // Route model binding slug bulamazsa 404 döner
        $response->assertStatus(404);
    }
}
