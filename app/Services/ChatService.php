<?php

namespace App\Services;

use App\Models\ChatSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ChatService
{
    public function __construct(
        private OllamaService $ollama,
        private ChatPromptBuilder $promptBuilder,
    ) {}

    /**
     * Kullanıcıdan gelen mesajı işler, LLM'e (Ollama) gönderir ve cevabı kaydeder.
     */
    public function processMessage(User $user, ?Store $store, string $messageContent): Model
    {
        // 1. İlgili kullanıcı ve mağaza (veya platform geneli) için aktif bir oturum bul veya oluştur
        $session = ChatSession::firstOrCreate([
            'user_id' => $user->id,
            'store_id' => $store?->id,
        ]);

        // 2. Kullanıcının yeni mesajını veritabanına kaydet
        $session->messages()->create([
            'role' => 'user',
            'content' => $messageContent,
        ]);

        // 3. System prompt'unu oluştur (bkz. ChatPromptBuilder).
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

        // 6. Ollama'nın TAM cevabını bekle, sonra veritabanına kaydet.
        $reply = $this->ollama->chat($messages);

        return $session->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);
    }
}
