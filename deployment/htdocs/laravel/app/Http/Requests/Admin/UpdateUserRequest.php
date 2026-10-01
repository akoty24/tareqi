<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Staff correction of a user's account details. */
class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^01[0125][0-9]{8}$/', Rule::unique('users', 'phone')->ignore($userId)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'email_verified' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => preg_replace('/\D/', '', (string) $this->input('phone'))]);
        }
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }
}
