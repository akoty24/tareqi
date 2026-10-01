<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Vehicle */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_type' => $this->vehicle_type->value,
            'model' => $this->model,
            'color' => $this->color,
            'plate_number' => $this->plate_number,
            'trips_count' => $this->whenCounted('trips'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
