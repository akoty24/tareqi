<?php

namespace App\Listeners;

use App\Enums\BookingStatus;
use App\Events\BookingStatusChanged;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingRejectedNotification;

class SendBookingStatusNotifications
{
    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking->loadMissing(['trip.owner', 'passenger']);

        match ($booking->status) {
            BookingStatus::Confirmed => $booking->passenger->notify(new BookingConfirmedNotification($booking)),
            BookingStatus::Rejected => $booking->passenger->notify(new BookingRejectedNotification($booking)),
            BookingStatus::Cancelled => $this->notifyOtherParty($event),
            default => null,
        };
    }

    private function notifyOtherParty(BookingStatusChanged $event): void
    {
        $booking = $event->booking;
        $byPassenger = $event->actor !== null && $booking->isPassenger($event->actor);

        $recipient = $byPassenger ? $booking->trip->owner : $booking->passenger;
        $recipient->notify(new BookingCancelledNotification($booking, $byPassenger));
    }
}
