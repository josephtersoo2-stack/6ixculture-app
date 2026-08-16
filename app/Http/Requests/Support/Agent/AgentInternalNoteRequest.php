<?php

namespace App\Http\Requests\Support\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentInternalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }
}
