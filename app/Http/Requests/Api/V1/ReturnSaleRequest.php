<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReturnSaleRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'refund_method' => ['nullable', 'string', 'in:cash,card,transfer,offset_debt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required for return.',
            'items.*.product_id.required' => 'Product ID is required for each return item.',
            'items.*.product_id.exists' => 'Product not found.',
            'items.*.quantity.required' => 'Quantity is required for each return item.',
            'items.*.quantity.gt' => 'Return quantity must be greater than zero.',
            'refund_method.in' => 'Refund method must be cash, card, transfer, or offset_debt.',
        ];
    }
}
