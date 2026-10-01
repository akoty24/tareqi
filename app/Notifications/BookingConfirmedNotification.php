<?php

namespace App\Notifications;

class BookingConfirmedNotification extends BookingNotification
{
    protected bool $mail = true;

    public function type(): string
    {
        return 'booking_confirmed';
    }
}
