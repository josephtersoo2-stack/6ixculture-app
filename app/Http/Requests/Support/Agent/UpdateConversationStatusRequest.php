<?php

namespace App\Http\Requests\Support\Agent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:new,ai_active,queued,human_active,awaiting_customer,awaiting_agent,resolved,closed'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
