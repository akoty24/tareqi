<?php

namespace App\Events;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public BookingStatus $previousStatus,
        public ?User $actor = null,
    ) {
    }
}
