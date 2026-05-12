<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'id' => ['nullable', 'string', 'max:36'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'string', 'in:cash,card,transfer'],
            // `type` is kept for backward-compat but the server now derives it
            // from the items themselves: any product line ⇒ "product", else
            // "service". A single sale can mix both kinds (e.g. TV + setup),
            // see StoreSaleRequest::messages for the per-item contract.
            'type' => ['nullable', 'string', 'in:product,service'],
            'items' => ['required', 'array', 'min:1'],
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

                    // Service line — price is the user-typed labour rate; we
                    // only require it to be non-negative (handled by `min:0`).
                    if ($productId === null) {
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
     * Cross-field check: a service line (no `product_id`) must carry a
     * non-empty `name`. Putting this in `withValidator` (rather than as a
     * closure on `items.*.name`) guarantees the check runs even when the
     * client omits the `name` key entirely — the per-field closure is
     * skipped by Laravel for missing nullable fields.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $items = $this->input('items', []);
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
            'items.required' => 'Добавьте хотя бы один товар в продажу.',
            'items.*.price.required' => 'Укажите цену для каждой позиции.',
            'items.*.quantity.required' => 'Укажите количество для каждой позиции.',
            // Soft-deleted products fail `exists:products,id`. The cashier
            // usually hits this when local cache still has a product the
            // admin removed on the backend — friendlier copy makes the
            // fix path obvious (refresh the catalog).
            'items.*.product_id.required' => 'Выберите товар для каждой позиции.',
            'items.*.product_id.exists' => 'Товар не найден или был удалён. Обновите каталог и попробуйте снова.',
        ];
    }
}
