<?php

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chat API'nin validation ve hata davranışı testleri.
 *
 * NOT: Chatbot'un gerçek LLM cevabını test ETMIYORUZ — çünkü:
 * 1. Ollama her zaman açık olmayabilir (CI/CD ortamında kesinlikle olmaz)
 * 2. LLM cevapları deterministik değil (aynı soruya farklı cevap verebilir)
 * 3. LLM cevap süresi çok uzun (test suite'i yavaşlatır)
 *
 * Bunun yerine: doğru istek yapısı gönderildiğinde validation'ın geçtiğini,
 * yanlış istek yapısında 422 Unprocessable Entity döndüğünü test ediyoruz.
 * Bu, "controller katmanı doğru çalışıyor mu?" sorusunu cevaplar.
 */
class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_requires_user_id(): void
    {
        $response = $this->postJson('/api/chat', [
            'message' => 'Merhaba',
        ]);

        // user_id olmadan 422 (validation error) dönmeli
        $response->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_chat_requires_message(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $response = $this->postJson('/api/chat', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_rejects_nonexistent_user(): void
    {
        $response = $this->postJson('/api/chat', [
            'user_id' => 99999,
            'message' => 'Merhaba',
        ]);

        // exists:users,id kuralı devreye girmeli
        $response->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_chat_rejects_nonexistent_store(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $response = $this->postJson('/api/chat', [
            'user_id' => $user->id,
            'message' => 'Merhaba',
            'store_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_chat_rejects_empty_message(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $response = $this->postJson('/api/chat', [
            'user_id' => $user->id,
            'message' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_rejects_too_long_message(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        // max:1000 kuralı — 1001 karakterlik mesaj reddedilmeli
        $response = $this->postJson('/api/chat', [
            'user_id' => $user->id,
            'message' => str_repeat('a', 1001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_accepts_valid_store_id_as_optional(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        // store_id nullable olduğu için, var olan bir store_id ile
        // validation geçmeli (ama Ollama kapalıysa 500 dönebilir,
        // o yüzden sadece validation'ın geçtiğini kontrol ediyoruz)
        $response = $this->postJson('/api/chat', [
            'user_id' => $user->id,
            'message' => 'Merhaba',
            'store_id' => $store->id,
        ]);

        // Validation geçti = 422 DEĞİL
        // (Ollama kapalıysa 500 dönebilir, ama 422 dönmemeli)
        $this->assertNotEquals(422, $response->status());
    }
}
