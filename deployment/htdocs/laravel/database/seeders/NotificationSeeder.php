<?php

namespace Database\Seeders;

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\NewBookingRequestNotification;
use App\Notifications\TripRequestMatchedNotification;
use Illuminate\Database\Seeder;

/** A few database notifications so the demo accounts have something to read. */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $driver = User::where('email', 'driver@mishwar.test')->firstOrFail();
        $sara = User::where('email', 'passenger@mishwar.test')->firstOrFail();

        $booking = Booking::query()
            ->where('passenger_id', $sara->id)
            ->whereHas('trip', fn ($q) => $q->where('owner_id', $driver->id)->where('status', TripStatus::Published))
            ->first();

        if ($booking) {
            $sara->notifyNow(new BookingCreatedNotification($booking), ['database']);
            $driver->notifyNow(new NewBookingRequestNotification($booking), ['database']);
        }

        $request = $sara->tripRequests()->first();
        $trip = $driver->trips()->where('status', TripStatus::Published)->first();
        if ($request && $trip) {
            $sara->notifyNow(new TripRequestMatchedNotification($request, $trip, 85), ['database']);
        }
    }
}
