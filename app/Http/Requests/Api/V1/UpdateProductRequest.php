<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shopId = $this->route('product')?->shop_id;
        $productId = $this->route('product')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'code')
                    ->where(fn ($query) => $query->where('shop_id', $shopId))
                    ->ignore($productId),
            ],
            'unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'low_stock_alert' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'image' => ['sometimes', 'nullable', File::image()->max(5 * 1024)],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'cost_price.required' => 'Cost price is required.',
            'sale_price.required' => 'Sale price is required.',
            'stock_quantity.required' => 'Stock quantity is required.',
        ];
    }
}
