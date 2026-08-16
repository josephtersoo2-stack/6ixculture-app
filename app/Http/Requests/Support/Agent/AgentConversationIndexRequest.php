<?php

namespace App\Http\Requests\Support\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentConversationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorize in controller or via gate/policy
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:32'],
            'department_id' => ['nullable', 'integer', 'exists:support_departments,id'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'assigned_to' => ['nullable', 'string', 'max:32'],
            'unassigned' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:8'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
