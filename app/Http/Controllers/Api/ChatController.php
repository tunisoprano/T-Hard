<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendChatMessageRequest;
use App\Models\ChatSession;
use App\Models\Store;
use App\Models\User;
use App\Services\ChatPromptBuilder;
use App\Services\OllamaService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private OllamaService $ollama,
        private ChatPromptBuilder $promptBuilder,
    ) {}

    public function respond(SendChatMessageRequest $request): StreamedResponse
    {
        $data = $request->validated();

        $user = User::findOrFail($data['user_id']);

        // 1. İlgili kullanıcı ve mağaza (veya platform geneli) için aktif bir oturum bul veya oluştur
        $session = ChatSession::firstOrCreate([
            'user_id' => $user->id,
            'store_id' => $data['store_id'] ?? null,
        ]);

        // 2. Kullanıcının yeni mesajını veritabanına kaydet
        $session->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        // 3. System prompt'unu oluştur (bkz. ChatPromptBuilder). Kullanıcıya
        // özel geçmiş/öneri bilgisi artık canlı DB sorgusuyla değil,
        // önceden üretilmiş context dosyasından okunuyor.
        $store = isset($data['store_id']) ? Store::findOrFail($data['store_id']) : null;
        $systemPrompt = $this->promptBuilder->build($user, $store);

        // 4. Ollama'ya gidecek mesaj listesini hazırla
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // 5. Veritabanındaki eski mesajları sırasıyla listeye ekle
        $history = $session->messages()->latest()->take(10)->get()->reverse();
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        // 6. Cevabı parça parça tarayıcıya akıt, aynı anda tam metni de biriktir.
        // response()->stream() PHP'nin çıktı arabelleğini (output buffer) kapatıp
        // her echo'yu anında istemciye gönderiyor — normal bir response'ta tüm
        // içerik hazır olana kadar tarayıcı hiçbir şey görmezdi.
        return response()->stream(function () use ($messages, $session) {
            $fullReply = $this->ollama->chatStream($messages, function (string $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });

            // 7. Akış bitince, tam cevabı veritabanına kaydet
            $session->messages()->create([
                'role' => 'assistant',
                'content' => $fullReply,
            ]);
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            // nginx (Herd) yanıtı arabelleğe almasın, parçalar geldikçe göndersin
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
