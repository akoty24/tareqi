<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Notifications\TripApproachingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Scheduled every 15 minutes: remind owners and confirmed passengers of trips
 * departing within the next REMIND_BEFORE_MINUTES. Each trip is reminded once
 * (trips.reminder_sent_at). Return trips get the "return trip approaching" text.
 */
class SendTripReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const REMIND_BEFORE_MINUTES = 180;

    public function handle(): void
    {
        $now = now();
        $until = $now->copy()->addMinutes(self::REMIND_BEFORE_MINUTES);

        Trip::query()
            ->whereIn('status', [TripStatus::Published, TripStatus::Full])
            ->whereNull('reminder_sent_at')
            ->whereDate('departure_date', '>=', $now->toDateString())
            ->whereDate('departure_date', '<=', $until->toDateString())
            ->with(['owner', 'bookings' => fn ($q) => $q->where('status', BookingStatus::Confirmed)->with('passenger')])
            ->get()
            ->filter(fn (Trip $trip) => $trip->departureAt()->between($now, $until))
            ->each(function (Trip $trip) {
                $trip->owner->notify(new TripApproachingNotification($trip, forOwner: true));
                Notification::send($trip->bookings->pluck('passenger'), new TripApproachingNotification($trip));

                $trip->forceFill(['reminder_sent_at' => now()])->save();
            });
    }
}
