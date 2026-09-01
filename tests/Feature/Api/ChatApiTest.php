<?php

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chat API'nin validation ve hata davranışı testleri.
 *
 * NOT: Gerçek Ollama'ya İSTEK ATMIYORUZ — çünkü:
 * 1. Ollama her zaman açık olmayabilir (CI/CD ortamında kesinlikle olmaz)
 * 2. LLM cevapları deterministik değil (aynı soruya farklı cevap verebilir)
 * 3. LLM cevap süresi çok uzun (test suite'i yavaşlatır)
 *
 * Validation testlerinde OllamaService hiç çağrılmaz (422 validation'da
 * durur). Gerçek 200 senaryosunda ise OllamaService'i mock'layıp sahte
 * bir cevap döndürüyoruz — böylece controller/resource akışını Ollama'ya
 * hiç gitmeden test edebiliyoruz.
 */
class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ChatController artık prompt oluştururken context dosyasını okuyor
        // (bkz. UserContextGenerator) — testler gerçek storage/app'e değil
        // sahte bir diske baksın.
        Storage::fake('local');
    }

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

    public function test_chat_accepts_valid_store_id_and_returns_assistant_message(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        // OllamaService'i mock'luyoruz — gerçek Ollama'ya hiç gitmiyoruz,
        // sahte bir cevap döndürüyor. Böylece 200 + doğru JSON şeklini
        // (ChatMessageResource) deterministik şekilde test edebiliyoruz.
        $this->mock(OllamaService::class, function ($mock) {
            $mock->shouldReceive('chat')->once()->andReturn('SahteAsistanCevabiXYZ');
        });

        $response = $this->postJson('/api/chat', [
            'user_id' => $user->id,
            'message' => 'Merhaba',
            'store_id' => $store->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'role' => 'assistant',
                'content' => 'SahteAsistanCevabiXYZ',
            ]);
    }

    /**
     * Prompt oluşturma mantığı artık ChatPromptBuilder servisinde, public
     * bir metod olarak yaşıyor — Reflection'a gerek yok, servisi doğrudan
     * çağırıp context dosyasının gerçekten prompt'a gömüldüğünü kanıtlıyoruz.
     * DB'de hiçbir sipariş/öneri kaydı YOK — eğer prompt DB'ye gidiyor
     * olsaydı bu içerik asla çıkmazdı, sadece elle yazdığımız context
     * dosyasından gelebilir.
     */
    public function test_store_prompt_is_built_from_context_file_not_live_db(): void
    {
        $store = Store::factory()->create(['name' => 'Test Mağaza']);
        $user = User::factory()->create(['store_id' => $store->id]);

        $generator = app(\App\Services\UserContextGenerator::class);
        Storage::disk('local')->put(
            "user-contexts/{$store->id}/{$user->id}.md",
            "# Kullanıcı Bağlamı\nBenzersizTestCumlesiXYZ123"
        );

        $promptBuilder = app(\App\Services\ChatPromptBuilder::class);
        $prompt = $promptBuilder->build($user, $store);

        $this->assertStringContainsString('BenzersizTestCumlesiXYZ123', $prompt);
    }

    /**
     * Platform geneli prompt artık dosya sistemi taranarak değil, önceden
     * üretilmiş TEK ana dosyadan (bkz. UserContextGenerator::regenerateMaster)
     * okunuyor. Bu yüzden mağaza dosyalarını yazdıktan sonra ana dosyayı
     * da elle üretmemiz gerekiyor — gerçek akışta bunu context:generate
     * komutu veya checkout sonrası OrderService yapıyor.
     */
    public function test_personal_prompt_combines_all_of_the_users_store_context_files(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $storeA->id]);

        $generator = app(\App\Services\UserContextGenerator::class);
        Storage::disk('local')->put("user-contexts/{$storeA->id}/{$user->id}.md", 'MagazaA_BenzersizIcerik');
        Storage::disk('local')->put("user-contexts/{$storeB->id}/{$user->id}.md", 'MagazaB_BenzersizIcerik');
        $generator->regenerateMaster($user);

        $promptBuilder = app(\App\Services\ChatPromptBuilder::class);
        $prompt = $promptBuilder->build($user, null);

        $this->assertStringContainsString('MagazaA_BenzersizIcerik', $prompt);
        $this->assertStringContainsString('MagazaB_BenzersizIcerik', $prompt);
    }
}
