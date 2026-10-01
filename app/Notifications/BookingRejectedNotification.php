<?php

namespace App\Notifications;

class BookingRejectedNotification extends BookingNotification
{
    public function type(): string
    {
        return 'booking_rejected';
    }

    public function link(): ?string
    {
        return '/search';
    }
}
