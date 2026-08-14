<?php

namespace App\Http\Requests;

use App\Models\ProductVariation;
use App\Rules\IniAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('sku') && $this->sku !== null) {
            $merge['sku'] = (string) $this->sku;
        }
        if ($this->has('name') && $this->name !== null) {
            $merge['name'] = (string) $this->name;
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'                       => [
                'nullable',
                'string',
                'max:190',
            ],
            'sku'                        => [
                'nullable',
                'string',
                'max:190',
            ],
            'product_category_id'        => ['nullable'],
            'barcode_id'                 => ['nullable'],
            'buying_price'               => ['nullable'],
            'selling_price'              => ['nullable'],
            'tax_id[]'                   => ['nullable'],
            'product_brand_id'           => ['nullable'],
            'status'                     => ['nullable'],
            'can_purchasable'            => ['nullable'],
            'show_stock_out'             => ['nullable'],
            'refundable'                 => ['nullable'],
            'maximum_purchase_quantity'  => ['nullable'],
            'low_stock_quantity_warning' => ['nullable'],
            'unit_id'                    => ['nullable'],
            'weight'                     => ['nullable', 'string', 'max:100'],
            'warranty'                   => ['nullable', 'string', 'max:100'],
            'description'                => ['nullable', 'string', 'max:5000'],
            'tags'                       => ['nullable'],
            'image'                      => ['nullable'],
            'seo_title'                  => ['nullable', 'string', 'max:255'],
            'seo_description'            => ['nullable', 'string', 'max:5000'],
            'seo_meta_keywords'          => ['nullable'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_category_id' => strtolower(trans('all.label.product_category_id')),
            'product_brand_id'    => strtolower(trans('all.label.product_brand_id')),
            'barcode_id'          => strtolower(trans('all.label.barcode_id')),
            'unit_id'             => strtolower(trans('all.label.unit_id')),
            'tax_id'              => strtolower(trans('all.label.tax_id')),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sku = ProductVariation::where('sku', $this->sku)->first();
            if ($sku) {
                $validator->getMessageBag()->add('sku', trans('all.message.sku_exist'));
            }
        });
    }
}
