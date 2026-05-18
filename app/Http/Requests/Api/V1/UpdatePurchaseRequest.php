<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
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
     * Mirrors StorePurchaseRequest but every field is optional — partial
     * updates are allowed (e.g. supplier rename without touching items).
     * When `items` IS sent it must be a non-empty list and follow the
     * same per-item shape; the service rolls back old stock and applies
     * the new lines atomically.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'string', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.min' => 'Добавьте хотя бы один товар в приход.',
            'items.*.product_id.required_with' => 'Выберите товар для каждой позиции.',
            'items.*.product_id.exists' => 'Товар не найден или был удалён. Обновите каталог и попробуйте снова.',
            'items.*.quantity.required_with' => 'Укажите количество для каждой позиции.',
            'items.*.price.required_with' => 'Укажите цену для каждой позиции.',
        ];
    }
}
