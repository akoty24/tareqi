<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    use TripRules;

    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'integer', $this->vehicleRule()],
            'origin' => ['sometimes', 'string', 'min:2', 'max:120'],
            'destination' => ['sometimes', 'string', 'min:2', 'max:120'],
            'departure_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:today'],
            'departure_time' => ['sometimes', 'date_format:H:i'],
            ...$this->pricingRules('sometimes'),
        ];
    }
}
