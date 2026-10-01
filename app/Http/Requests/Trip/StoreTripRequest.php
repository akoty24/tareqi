<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    use TripRules;

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', $this->vehicleRule()],
            'origin' => ['required', 'string', 'min:2', 'max:120'],
            'destination' => ['required', 'string', 'min:2', 'max:120', 'different:origin'],
            'departure_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'departure_time' => ['required', 'date_format:H:i'],
            ...$this->pricingRules('required'),
            'publish' => ['sometimes', 'boolean'],

            'return_trip' => ['nullable', 'array'],
            'return_trip.departure_date' => ['required_with:return_trip', 'date_format:Y-m-d', 'after_or_equal:departure_date'],
            'return_trip.departure_time' => ['required_with:return_trip', 'date_format:H:i'],
            'return_trip.total_seats' => ['nullable', 'integer', 'min:1', 'max:14'],
            'return_trip.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
