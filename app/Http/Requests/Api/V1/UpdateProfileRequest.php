<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($userId),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            // Required only when changing password — and must verify against
            // the user's actual current password.
            'current_password' => [
                Rule::requiredIf(fn () => $this->filled('password')),
                'current_password',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Введите текущий пароль для смены пароля.',
            'current_password.current_password' => 'Текущий пароль указан неверно.',
            'password.min' => 'Новый пароль должен быть не менее 8 символов.',
            'email.unique' => 'Этот email уже используется.',
        ];
    }
}
