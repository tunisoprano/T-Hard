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
        $response = Http::timeout(60)
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

    /**
     * Ollama'dan cevabı parça parça (token/kelime öbekleri hâlinde) alır.
     * Ollama'nın stream:true modu, her satırı ayrı bir JSON nesnesi olan
     * NDJSON (newline-delimited JSON) formatında cevap veriyor — biz de
     * satır satır okuyup içindeki metin parçasını $onChunk'a veriyoruz.
     *
     * @param  callable(string): void  $onChunk  her parça geldiğinde çağrılır
     * @return string  tamamlanan cevabın tam metni (DB'ye kaydetmek için)
     */
    public function chatStream(array $messages, callable $onChunk): string
    {
        $response = Http::withOptions(['stream' => true])
            ->timeout(120)
            ->post(config('services.ollama.url').'/api/chat', [
                'model' => config('services.ollama.model'),
                'messages' => $messages,
                'stream' => true,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Ollama isteği başarısız oldu: '.$response->body());
        }

        $body = $response->toPsrResponse()->getBody();
        $fullReply = '';
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);

                if ($line === '') {
                    continue;
                }

                $chunk = json_decode($line, true);
                $delta = $chunk['message']['content'] ?? '';

                if ($delta !== '') {
                    $fullReply .= $delta;
                    $onChunk($delta);
                }
            }
        }

        return $fullReply;
    }
}
