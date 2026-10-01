<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TripRequest */
class TripRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'requested_date' => $this->requested_date->toDateString(),
            'preferred_time_from' => $this->preferred_time_from ? substr($this->preferred_time_from, 0, 5) : null,
            'preferred_time_to' => $this->preferred_time_to ? substr($this->preferred_time_to, 0, 5) : null,
            'passengers_count' => $this->passengers_count,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'matched_trips_count' => $this->whenCounted('matchedTrips'),
            'user' => new PublicUserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
