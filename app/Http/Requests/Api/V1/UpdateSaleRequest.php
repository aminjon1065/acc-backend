<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
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
        $type = $this->input('type');
        $isProductType = $type !== 'service';

        return [
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'string', 'in:cash,card,transfer'],
            'type' => ['nullable', 'string', 'in:product,service'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'items.*.name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'items.*.unit' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.min' => 'At least one sale item is required.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
        ];
    }
}
