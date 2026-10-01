<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Trip */
class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isOwner = $this->isOwnedBy($viewer);
        $viewerBooking = $this->relationLoaded('viewerBooking') ? $this->getRelation('viewerBooking') : null;
        $viewerConfirmed = $viewerBooking && in_array($viewerBooking->status, [BookingStatus::Confirmed, BookingStatus::Completed], true);

        return [
            'id' => $this->id,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure_date' => $this->departure_date->toDateString(),
            'departure_time' => substr($this->departure_time, 0, 5),
            'departure_at' => $this->departureAt()->toIso8601String(),
            'total_seats' => $this->total_seats,
            'available_seats' => $this->available_seats,
            'booked_seats' => $this->bookedSeats(),
            'cost_type' => $this->cost_type->value,
            'price_per_seat' => $this->price_per_seat !== null ? (float) $this->price_per_seat : null,
            'estimated_cost_per_passenger' => $this->estimated_cost_per_passenger !== null ? (float) $this->estimated_cost_per_passenger : null,
            'seat_price' => (float) $this->currentSeatPrice(),
            'notes' => $this->notes,
            'auto_confirm_bookings' => $this->auto_confirm_bookings,
            'status' => $this->status->value,
            'is_return_trip' => $this->isReturnTrip(),
            'parent_trip_id' => $this->parent_trip_id,
            'cancellation_reason' => $this->cancellation_reason,
            'is_mine' => $isOwner,
            'pending_bookings_count' => $this->whenHas('pending_bookings_count'),
            'bookings_count' => $this->whenCounted('bookings'),

            'owner' => new PublicUserResource($this->whenLoaded('owner')),
            // Contact details only for the owner themself and confirmed passengers.
            'owner_phone' => $this->when(
                ($isOwner || $viewerConfirmed) && $this->relationLoaded('owner'),
                fn () => $this->owner->phone,
            ),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'return_trip' => new TripResource($this->whenLoaded('returnTrip')),
            'parent_trip' => new TripResource($this->whenLoaded('parentTrip')),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'my_booking' => $this->when($this->relationLoaded('viewerBooking'), fn () => $viewerBooking ? new BookingResource($viewerBooking) : null),
            'match' => $this->when($this->resource->match !== null, fn () => $this->resource->match),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
