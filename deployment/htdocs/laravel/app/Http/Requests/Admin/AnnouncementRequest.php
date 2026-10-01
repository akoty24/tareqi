<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnnouncementAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'message' => ['required', 'string', 'min:3', 'max:1000'],
            // In-app path only (e.g. /search), never an external URL.
            'link' => ['nullable', 'string', 'max:255', 'regex:#^/[A-Za-z0-9/_\-?=&.]*$#'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'user_ids' => ['required_if:audience,'.AnnouncementAudience::Selected->value, 'array', 'max:500'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'send_email' => ['sometimes', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();
        if (($data['audience'] ?? null) !== AnnouncementAudience::Selected->value) {
            $data['user_ids'] = null;
        }
        $data['send_email'] = (bool) ($data['send_email'] ?? false);

        return data_get($data, $key, $default);
    }
}
