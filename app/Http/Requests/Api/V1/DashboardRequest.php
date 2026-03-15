<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['day', 'week', 'month', 'year', 'custom'])],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date', 'required_if:period,custom'],
            'date_to' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:date_from'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
        ];
    }
}
