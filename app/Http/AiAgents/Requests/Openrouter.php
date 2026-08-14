<?php

namespace App\Http\AiAgents\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Openrouter extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'openrouter_api_key'      => ['nullable', 'string'],
            'openrouter_model'        => ['nullable', 'string'],
            'openrouter_custom_model' => ['nullable', 'string'],
            'openrouter_status'       => ['required', 'numeric'],
        ];
    }
}
