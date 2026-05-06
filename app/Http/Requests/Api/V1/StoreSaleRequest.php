<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'id' => ['nullable', 'string', 'max:36'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'string', 'in:cash,card,transfer'],
            'type' => ['nullable', 'string', 'in:product,service'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                'string',
                'exists:products,id',
                Rule::requiredIf($isProductType),
            ],
            'items.*.name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'items.*.unit' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $itemIndex = preg_replace('/[^0-9]/', '', $attribute);
                    $productId = $this->input("items.{$itemIndex}.product_id");
                    $type = $this->input('type');

                    if ($type === 'service' || $productId === null) {
                        return;
                    }

                    $price = (float) $value;
                    if ($price <= 0) {
                        $fail('Price must be greater than zero for product items.');

                        return;
                    }

                    $product = \App\Models\Product::find((string) $productId);
                    if ($product && $product->cost_price !== null && $price < (float) $product->cost_price) {
                        $fail("Price cannot be below cost price ({$product->cost_price}) for product \"{$product->name}\".");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one sale item is required.',
            'items.*.price.required' => 'Price is required for each item.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
        ];
    }
}
