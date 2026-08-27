<?php

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_chat_accepts_valid_store_id_as_optional(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        // store_id nullable olduğu için, var olan bir store_id ile
        // validation geçmeli. Endpoint artık StreamedResponse döndürüyor
        // (streaming) — bu yüzden status() değil, ham getStatusCode()
        // kullanıyoruz; başlıklar (dolayısıyla 200) callback çalışmadan
        // hemen gönderildiği için Ollama kapalı olsa bile burada hâlâ 200
        // görürüz, önemli olan 422 (validation hatası) dönmediğini görmek.
        $response = $this->postJson('/api/chat', [
            'user_id' => $user->id,
            'message' => 'Merhaba',
            'store_id' => $store->id,
        ]);

        $this->assertNotEquals(422, $response->baseResponse->getStatusCode());
    }

    /**
     * Prompt oluşturma metodları private olduğu için (ve StreamedResponse
     * callback'i test client'ında hiç çalışmadığı için — bkz. yukarıdaki
     * not), bunları Reflection ile doğrudan çağırıp context dosyasının
     * gerçekten prompt'a gömüldüğünü kanıtlıyoruz. DB'de hiçbir sipariş/
     * öneri kaydı YOK — eğer prompt DB'ye gidiyor olsaydı bu içerik asla
     * çıkmazdı, sadece elle yazdığımız context dosyasından gelebilir.
     */
    public function test_store_prompt_is_built_from_context_file_not_live_db(): void
    {
        $store = Store::factory()->create(['name' => 'Test Mağaza']);
        $user = User::factory()->create(['store_id' => $store->id]);

        $generator = app(\App\Services\UserContextGenerator::class);
        Storage::disk('local')->put(
            $generator->path($user, $store),
            "# Kullanıcı Bağlamı\nBenzersizTestCumlesiXYZ123"
        );

        $controller = app(\App\Http\Controllers\Api\ChatController::class);
        $method = new \ReflectionMethod($controller, 'buildStorePrompt');
        $method->setAccessible(true);
        $prompt = $method->invoke($controller, $store, $user);

        $this->assertStringContainsString('BenzersizTestCumlesiXYZ123', $prompt);
    }

    public function test_personal_prompt_combines_all_of_the_users_store_context_files(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $storeA->id]);

        $generator = app(\App\Services\UserContextGenerator::class);
        Storage::disk('local')->put($generator->path($user, $storeA), 'MagazaA_BenzersizIcerik');
        Storage::disk('local')->put($generator->path($user, $storeB), 'MagazaB_BenzersizIcerik');

        $controller = app(\App\Http\Controllers\Api\ChatController::class);
        $method = new \ReflectionMethod($controller, 'buildPersonalPrompt');
        $method->setAccessible(true);
        $prompt = $method->invoke($controller, $user);

        $this->assertStringContainsString('MagazaA_BenzersizIcerik', $prompt);
        $this->assertStringContainsString('MagazaB_BenzersizIcerik', $prompt);
    }
}
