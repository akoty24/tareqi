<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Real availability is checked under a row lock in BookingService.
            'seats' => ['required', 'integer', 'min:1', 'max:14'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
