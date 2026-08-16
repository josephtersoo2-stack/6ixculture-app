<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tool_name' => ['required', 'string', 'max:64'],
            'arguments' => ['required', 'array'],
            'confirmed' => ['required', 'boolean'],
            'guest_token' => ['nullable', 'string', 'max:64'],
        ];
    }
}
