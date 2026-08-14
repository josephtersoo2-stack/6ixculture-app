<?php

namespace App\Http\AiAgents\Requests;

use App\Enums\Activity;
use Illuminate\Foundation\Http\FormRequest;

class Gemini extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'gemini_api_key'      => ['nullable', 'string'],
            'gemini_model'        => ['nullable', 'string'],
            'gemini_custom_model' => ['nullable', 'string'],
            'gemini_status'       => ['required', 'numeric'],
        ];
    }
}
