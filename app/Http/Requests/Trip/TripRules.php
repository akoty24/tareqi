<?php

namespace App\Http\Requests\Trip;

use App\Enums\CostType;
use Illuminate\Validation\Rule;

/** Validation rules shared by trip create / update / return-trip requests. */
trait TripRules
{
    protected function vehicleRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('vehicles', 'id')
            ->where('user_id', $this->user()->id)
            ->whereNull('deleted_at');
    }

    protected function pricingRules(string $required): array
    {
        return [
            'total_seats' => [$required, 'integer', 'min:1', 'max:14'],
            'cost_type' => [$required, Rule::enum(CostType::class)],
            'price_per_seat' => ['nullable', 'numeric', 'min:1', 'max:10000', 'required_if:cost_type,'.CostType::FixedPrice->value],
            'estimated_cost_per_passenger' => ['nullable', 'numeric', 'min:1', 'max:10000', 'required_if:cost_type,'.CostType::CostSharing->value],
            'notes' => ['nullable', 'string', 'max:1000'],
            'auto_confirm_bookings' => ['sometimes', 'boolean'],
        ];
    }
}
