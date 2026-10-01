<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

/** Route is the outbound route reversed; seats/pricing default to the outbound trip. */
class StoreReturnTripRequest extends FormRequest
{
    use TripRules;

    public function rules(): array
    {
        return [
            'departure_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'departure_time' => ['required', 'date_format:H:i'],
            ...$this->pricingRules('sometimes'),
        ];
    }
}
