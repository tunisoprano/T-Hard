<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Semantik arama API'sinin doğrulama (validation) davranışı testleri.
 *
 * NOT: Chat testlerindeki aynı sebeple, gerçek Ollama embedding isteğini
 * tetikleyen bir test yazmıyoruz (Ollama CI'da açık olmayabilir, sonuçlar
 * deterministik değil, istek yavaş). Sadece "q" parametresi eksik/geçersiz
 * olduğunda controller'ın Ollama'ya hiç gitmeden doğru şekilde 422
 * döndürdüğünü test ediyoruz.
 */
class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_query(): void
    {
        $response = $this->getJson('/api/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }

    public function test_search_rejects_too_short_query(): void
    {
        $response = $this->getJson('/api/search?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }

    public function test_search_rejects_nonexistent_store_id(): void
    {
        $response = $this->getJson('/api/search?q=pantolon&store_id=999999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
