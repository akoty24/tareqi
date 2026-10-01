<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full account view: only for the user themself or an admin.
 * Other users see PublicUserResource.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'profile_photo_url' => $this->profilePhotoUrl(),
            // Staff role (null for community members) and its effective permissions.
            'role' => $this->role ? new RoleResource($this->role) : null,
            'permissions' => $this->permissions(),
            'notification_settings' => [
                'email' => (bool) $this->notify_email,
                'push' => (bool) $this->notify_push,
            ],
            'status' => $this->status->value,
            'blocked_at' => $this->blocked_at?->toIso8601String(),
            'rating_average' => (float) $this->rating_average,
            'ratings_count' => $this->ratings_count,
            'completed_trips_as_owner' => $this->whenHas('completed_trips_as_owner_count'),
            'completed_trips_as_passenger' => $this->whenHas('completed_trips_as_passenger_count'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
