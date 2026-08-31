<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Store API endpoint'lerinin feature testleri.
 *
 * RefreshDatabase trait'i her test öncesi migration'ları çalıştırır ve
 * test sonrası DB'yi sıfırlar. Böylece testler birbirini etkilemez.
 */
class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_all_stores(): void
    {
        // ARRANGE: Test verisini hazırla
        Store::factory()->count(3)->create();

        // ACT: API'ye istek at
        $response = $this->getJson('/api/stores');

        // ASSERT: Beklenen sonucu kontrol et
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_store_response_has_correct_structure(): void
    {
        Store::factory()->create(['name' => 'T-Hard Market']);

        $response = $this->getJson('/api/stores');

        // Her store objesinde id ve name alanları olmalı
        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'T-Hard Market',
            ]);
    }

    public function test_can_list_store_products(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();

        // Bu mağazaya 3 ürün ekle
        Product::factory()->count(3)->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
        ]);

        // Başka mağazaya da 2 ürün ekle (bunlar sonuçta GÖRÜNMEMELİ)
        $otherStore = Store::factory()->create();
        Product::factory()->count(2)->create([
            'store_id' => $otherStore->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/stores/{$store->id}/products");

        // Sadece istenen mağazanın 3 ürünü dönmeli, diğer mağazanınkiler değil
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_store_products_by_category(): void
    {
        $store = Store::factory()->create();
        $pantolon = Category::factory()->create(['name' => 'Pantolon', 'slug' => 'pantolon']);
        $gomlek = Category::factory()->create(['name' => 'Gömlek', 'slug' => 'gomlek']);

        // Aynı mağazaya 2 pantolon, 1 gömlek ekle
        Product::factory()->count(2)->create([
            'store_id' => $store->id,
            'category_id' => $pantolon->id,
        ]);
        Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $gomlek->id,
        ]);

        // ?category=pantolon filtresi ile sadece pantolonlar gelmeli
        $response = $this->getJson("/api/stores/{$store->id}/products?category=pantolon");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_store_products_rejects_nonexistent_category(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create(['slug' => 'pantolon']);
        Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
        ]);

        // Olmayan bir kategori slug'ı artık sessizce boş dönmüyor,
        // StoreProductsRequest'teki exists:categories,slug kuralı 422 veriyor.
        $response = $this->getJson("/api/stores/{$store->id}/products?category=olmayan-kategori");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }
}
