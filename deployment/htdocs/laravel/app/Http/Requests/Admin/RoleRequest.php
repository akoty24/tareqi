<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'display_name' => [$required, 'string', 'min:2', 'max:100', Rule::unique('roles', 'display_name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => [$required, 'array', 'min:1'],
            'permissions.*' => ['string', 'distinct', Rule::enum(Permission::class)],
        ];
    }
}
