<?php

namespace App\Notifications;

/** To the trip owner: someone booked (needs approval unless auto-confirmed). */
class NewBookingRequestNotification extends BookingNotification
{
    protected bool $mail = true;

    public function type(): string
    {
        return $this->booking->status->value === 'confirmed' ? 'new_booking_confirmed' : 'new_booking_request';
    }
}
