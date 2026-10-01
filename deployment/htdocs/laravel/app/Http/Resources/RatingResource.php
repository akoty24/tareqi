<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Rating */
class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'stars' => $this->stars,
            'review' => $this->review,
            'rater' => new PublicUserResource($this->whenLoaded('rater')),
            'rated_user' => new PublicUserResource($this->whenLoaded('ratedUser')),
            'trip' => $this->whenLoaded('trip', fn () => [
                'id' => $this->trip->id,
                'origin' => $this->trip->origin,
                'destination' => $this->trip->destination,
                'departure_date' => $this->trip->departure_date->toDateString(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
