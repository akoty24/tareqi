<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validates a passenger's trip request (create via POST, update via PUT). */
class TripRequestRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'origin' => [$required, 'string', 'min:2', 'max:120'],
            'destination' => [$required, 'string', 'min:2', 'max:120'],
            'requested_date' => [$required, 'date_format:Y-m-d', 'after_or_equal:today'],
            'preferred_time_from' => ['nullable', 'date_format:H:i'],
            'preferred_time_to' => ['nullable', 'date_format:H:i', 'after_or_equal:preferred_time_from'],
            'passengers_count' => [$required, 'integer', 'min:1', 'max:14'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
