<?php

namespace App\Notifications;

use App\Models\Booking;

/**
 * Sent to the other party when a passenger or the trip owner cancels a
 * booking, or to both parties when the platform staff cancels it.
 */
class BookingCancelledNotification extends BookingNotification
{
    public function __construct(Booking $booking, public bool $cancelledByPassenger, public bool $cancelledByAdmin = false)
    {
        parent::__construct($booking);
    }

    public function type(): string
    {
        if ($this->cancelledByAdmin) {
            return 'booking_cancelled_by_admin';
        }

        return $this->cancelledByPassenger ? 'booking_cancelled_by_passenger' : 'booking_cancelled_by_owner';
    }
}
