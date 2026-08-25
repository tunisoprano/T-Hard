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
}
