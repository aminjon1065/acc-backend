<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
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
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Добавьте хотя бы один товар в приход.',
            'items.*.product_id.required' => 'Выберите товар для каждой позиции.',
            'items.*.product_id.exists' => 'Товар не найден или был удалён. Обновите каталог и попробуйте снова.',
            'items.*.quantity.required' => 'Укажите количество для каждой позиции.',
            'items.*.price.required' => 'Укажите цену для каждой позиции.',
        ];
    }
}
