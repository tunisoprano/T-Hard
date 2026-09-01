<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chatbot'un kaydedilmiş cevabını (ChatMessage modeli) JSON'a çevirir.
 * Streaming kaldırıldıktan sonra cevap artık tek seferde, tam hâliyle
 * geliyor — bu da onu bir Resource'a sarmayı anlamlı kılıyor (streaming
 * sırasında elimizde "bitmiş" bir model olmadığı için bu mümkün değildi).
 */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
