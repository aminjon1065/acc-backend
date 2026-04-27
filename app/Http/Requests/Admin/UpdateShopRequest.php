<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'      => ['sometimes', 'nullable', 'email', 'max:255'],
            'address'    => ['sometimes', 'nullable', 'string', 'max:500'],
            'status'     => ['sometimes', 'required', 'string', 'in:active,suspended'],
        ];
    }
}