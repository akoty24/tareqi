<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Email address or mobile number.
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $login = trim((string) $this->input('login'));
        $this->merge([
            'login' => str_contains($login, '@') ? mb_strtolower($login) : preg_replace('/\D/', '', $login),
        ]);
    }
}
