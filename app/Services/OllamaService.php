<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaService
{
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
