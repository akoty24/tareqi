<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Optional free-text reason (cancel trip, reject/cancel booking). */
class ReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
