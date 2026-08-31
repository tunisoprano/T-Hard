<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    /**
     * Projede auth sistemi yok, dolayısıyla yetkilendirilecek bir şey
     * yok — bu sınıf sadece validasyon kurallarını taşımak için var.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'message' => ['required', 'string', 'max:1000'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ];
    }
}
