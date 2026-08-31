<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRecommendationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['nullable', 'exists:stores,id'],
        ];
    }
}
