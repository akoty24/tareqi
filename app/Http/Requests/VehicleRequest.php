<?php

namespace App\Http\Requests;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for both creating (POST) and updating (PUT) a vehicle. */
class VehicleRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'vehicle_type' => [$required, Rule::enum(VehicleType::class)],
            'model' => [$required, 'string', 'max:80'],
            'color' => [$required, 'string', 'max:40'],
            'plate_number' => [$required, 'string', 'max:20'],
        ];
    }
}
