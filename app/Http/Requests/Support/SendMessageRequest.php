<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:1000'],
            'language' => ['nullable', 'string', 'in:en,yo,ig,ha'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
            'guest_token' => ['nullable', 'string', 'max:64'],
        ];
    }
}
