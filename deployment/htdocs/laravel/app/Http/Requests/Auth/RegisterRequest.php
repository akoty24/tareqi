<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            // Egyptian mobile: 010 / 011 / 012 / 015 + 8 digits.
            'phone' => ['required', 'string', 'regex:/^01[0125][0-9]{8}$/', 'unique:users,phone'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
