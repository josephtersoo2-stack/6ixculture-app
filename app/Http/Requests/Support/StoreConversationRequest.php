<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'in:en,yo,ig,ha'],
            'guest_token' => ['nullable', 'string', 'max:64'],
        ];
    }
}
