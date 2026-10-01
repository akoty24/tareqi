<?php

namespace App\Http\Requests\Trip;

use App\Enums\CostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchTripsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'origin' => ['nullable', 'string', 'max:120'],
            'destination' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:14'],
            'cost_type' => ['nullable', Rule::enum(CostType::class)],
            'flexible_dates' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
