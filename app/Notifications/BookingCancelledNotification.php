<?php

namespace App\Notifications;

use App\Models\Booking;

/** Sent to the other party when a passenger or the trip owner cancels a booking. */
class BookingCancelledNotification extends BookingNotification
{
    public function __construct(Booking $booking, public bool $cancelledByPassenger)
    {
        parent::__construct($booking);
    }

    public function type(): string
    {
        return $this->cancelledByPassenger ? 'booking_cancelled_by_passenger' : 'booking_cancelled_by_owner';
    }
}
