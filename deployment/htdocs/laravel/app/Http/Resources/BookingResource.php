<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $viewerIsTripOwner = $this->relationLoaded('trip') && $this->trip->isOwnedBy($viewer);
        $ratedByMe = $this->relationLoaded('ratings') && $viewer
            ? $this->ratings->contains('rater_id', $viewer->id)
            : null;

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'seats' => $this->seats,
            'status' => $this->status->value,
            'notes' => $this->notes,
            // Snapshot values stored at booking time.
            'price_per_seat' => (float) $this->price_per_seat,
            'total_price' => (float) $this->total_price,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'is_mine' => $this->isPassenger($viewer),

            'trip' => new TripResource($this->whenLoaded('trip')),
            // A confirmed passenger can contact the trip owner.
            'owner_phone' => $this->when(
                $this->isPassenger($viewer)
                    && in_array($this->status, [BookingStatus::Confirmed, BookingStatus::Completed], true)
                    && $this->relationLoaded('trip') && $this->trip->relationLoaded('owner'),
                fn () => $this->trip->owner->phone,
            ),
            'passenger' => new PublicUserResource($this->whenLoaded('passenger')),
            // The trip owner sees the phone of passengers they accepted.
            'passenger_phone' => $this->when(
                $viewerIsTripOwner && in_array($this->status, [BookingStatus::Confirmed, BookingStatus::Completed], true) && $this->relationLoaded('passenger'),
                fn () => $this->passenger->phone,
            ),
            'rated_by_me' => $this->when($ratedByMe !== null, $ratedByMe),
            'can_rate' => $this->when(
                $ratedByMe !== null,
                fn () => ! $ratedByMe && $this->status === BookingStatus::Completed,
            ),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
