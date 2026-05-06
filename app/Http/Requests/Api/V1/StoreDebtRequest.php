<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtRequest extends FormRequest
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
            'person_name' => ['required', 'string', 'max:255'],
            'direction' => ['nullable', 'string', 'in:receivable,payable'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_name.required' => 'Person name is required.',
        ];
    }
}
