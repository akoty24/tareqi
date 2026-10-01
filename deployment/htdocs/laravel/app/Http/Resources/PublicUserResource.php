<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What other community members may see about a user. No phone/email:
 * contact details are exposed only to confirmed trip participants
 * (see TripResource::owner_phone / BookingResource::passenger_phone).
 *
 * @mixin \App\Models\User
 */
class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'profile_photo_url' => $this->profilePhotoUrl(),
            'rating_average' => (float) $this->rating_average,
            'ratings_count' => $this->ratings_count,
            'completed_trips_as_owner' => $this->whenHas('completed_trips_as_owner_count'),
            'completed_trips_as_passenger' => $this->whenHas('completed_trips_as_passenger_count'),
            'member_since' => $this->created_at?->toDateString(),
        ];
    }
}
