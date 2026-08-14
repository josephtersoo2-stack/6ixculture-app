<?php

namespace App\Http\PaymentGateways\Requests;

use App\Enums\Activity;
use Illuminate\Foundation\Http\FormRequest;

class Monnify extends FormRequest
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
        if (request()->monnify_status == Activity::ENABLE) {
            return [
                'monnify_api_key'       => ['required', 'string'],
                'monnify_secret_key'    => ['required', 'string'],
                'monnify_contract_code' => ['required', 'string'],
                'monnify_mode'          => ['required', 'string'],
                'monnify_status'        => ['nullable', 'numeric'],
            ];
        } else {
            return [
                'monnify_api_key'       => ['nullable', 'string'],
                'monnify_secret_key'    => ['nullable', 'string'],
                'monnify_contract_code' => ['nullable', 'string'],
                'monnify_mode'          => ['nullable', 'string'],
                'monnify_status'        => ['nullable', 'numeric'],
            ];
        }
    }
}
