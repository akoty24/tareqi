<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\NewBookingRequestNotification;

class SendBookingCreatedNotifications
{
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking->loadMissing(['trip.owner', 'passenger']);

        $booking->passenger->notify(new BookingCreatedNotification($booking));
        $booking->trip->owner->notify(new NewBookingRequestNotification($booking));
    }
}
