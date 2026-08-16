<?php

namespace App\Http\Requests\Support\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'attachments' => ['nullable', 'array'],
            'resolve_after_reply' => ['nullable', 'boolean'],
        ];
    }
}
