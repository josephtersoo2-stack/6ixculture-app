<?php

namespace App\Http\Requests\Support\Agent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:support_departments,id'],
        ];
    }
}
