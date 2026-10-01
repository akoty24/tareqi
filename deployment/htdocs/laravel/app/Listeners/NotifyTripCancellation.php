<?php

namespace App\Listeners;

use App\Events\TripCancelled;
use App\Models\User;
use App\Notifications\TripCancelledNotification;
use Illuminate\Support\Facades\Notification;

class NotifyTripCancellation
{
    public function handle(TripCancelled $event): void
    {
        $passengerIds = $event->cancelledBookings->pluck('passenger_id')->unique();
        $recipients = User::whereIn('id', $passengerIds)->get();

        if ($event->byAdmin) {
            $recipients->push($event->trip->owner);
        }

        Notification::send($recipients, new TripCancelledNotification($event->trip, $event->byAdmin));
    }
}
