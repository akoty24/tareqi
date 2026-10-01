<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TripCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, Booking>  $cancelledBookings  bookings that were active when the trip was cancelled
     */
    public function __construct(
        public Trip $trip,
        public Collection $cancelledBookings,
        public bool $byAdmin = false,
    ) {
    }
}
