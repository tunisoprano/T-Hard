<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaService
{
    /**
     * Verilen metni sayısal bir vektöre (embedding) çevirir. Anlamca yakın
     * metinler, uzayda birbirine yakın vektörler üretir — bu sayede kelime
     * eşleşmesi olmasa bile ("kışlık" ile "mont" gibi) anlam yakınlığı
     * bulunabilir.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $response = Http::timeout(30)
            ->post(config('services.ollama.url').'/api/embeddings', [
                'model' => config('services.ollama.embedding_model'),
                'prompt' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Ollama embedding isteği başarısız oldu: '.$response->body());
        }

        return $response->json('embedding');
    }

    public function chat(array $messages): string
    {
        // Streaming olmadığı için (tek seferlik cevap), 7B modelin uzun bir
        // cevabı tamamen üretmesi zaman alabilir — timeout'u buna göre geniş
        // tutuyoruz.
        $response = Http::timeout(120)
            ->post(config('services.ollama.url').'/api/chat', [
                'model' => config('services.ollama.model'),
                'messages' => $messages,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Ollama isteği başarısız oldu: '.$response->body());
        }

        return $response->json('message.content');
    }
}
