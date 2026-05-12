<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'string', 'in:cash,card,transfer'],
            // See StoreSaleRequest — `type` is server-derived from items.
            'type' => ['nullable', 'string', 'in:product,service'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                'string',
                'exists:products,id',
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

                    if ($productId === null) {
                        return;
                    }

                    $price = (float) $value;
                    if ($price <= 0) {
                        $fail('Price must be greater than zero for product items.');

                        return;
                    }

                    $product = \App\Models\Product::find($productId);
                    if ($product && $product->cost_price !== null && $price < (float) $product->cost_price) {
                        $fail("Price cannot be below cost price ({$product->cost_price}) for product \"{$product->name}\".");
                    }
                },
            ],
        ];
    }

    /**
     * Cross-field check: a service line (no `product_id`) must carry a
     * non-empty `name`. See StoreSaleRequest::withValidator for rationale.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $items = $this->input('items');
            if (! is_array($items)) {
                return;
            }
            foreach ($items as $i => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $productId = $item['product_id'] ?? null;
                $name = $item['name'] ?? null;
                if ($productId === null && (! is_string($name) || trim($name) === '')) {
                    $validator->errors()->add(
                        "items.{$i}.name",
                        'Укажите название услуги.',
                    );
                }
            }
        });
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
