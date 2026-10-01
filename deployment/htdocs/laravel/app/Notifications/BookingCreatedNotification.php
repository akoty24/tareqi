<?php

namespace App\Notifications;

/** To the passenger: your booking was received (pending) or confirmed automatically. */
class BookingCreatedNotification extends BookingNotification
{
    public function type(): string
    {
        return $this->booking->status->value === 'confirmed' ? 'booking_created_confirmed' : 'booking_created_pending';
    }

    public function link(): ?string
    {
        return '/my-bookings';
    }
}
