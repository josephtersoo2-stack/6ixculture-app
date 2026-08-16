<?php

namespace App\Http\Requests\Support\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['nullable'], // integer user ID or null for unassign or 'self'
            'department_id' => ['nullable', 'integer', 'exists:support_departments,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
