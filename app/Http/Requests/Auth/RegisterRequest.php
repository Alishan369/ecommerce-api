<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Support\India;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesInput;

    protected function prepareForValidation(): void
    {
        $this->normalize(['email' => 'strtolower', 'phone' => India::phone(...)]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:'.India::PHONE_PATTERN],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'phone.regex' => India::PHONE_MESSAGE,
        ];
    }
}
