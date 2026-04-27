<?php

namespace App\Http\Requests\Admin;

use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roles = array_map(fn(UserRole $r) => $r->value, UserRole::cases());
        $userId = $this->route('user')?->id;

        return [
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role'     => ['sometimes', 'required', 'string', Rule::in($roles)],
            'shop_id'  => ['sometimes', 'nullable', 'integer', 'exists:shops,id'],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'This email is already taken.'];
    }
}