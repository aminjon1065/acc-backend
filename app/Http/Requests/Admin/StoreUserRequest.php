<?php

namespace App\Http\Requests\Admin;

use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roles = array_map(fn(UserRole $r) => $r->value, UserRole::cases());

        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'string', Rule::in($roles)],
            'shop_id'  => ['nullable', 'integer', 'exists:shops,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already taken.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}