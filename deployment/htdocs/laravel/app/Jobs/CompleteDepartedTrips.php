<?php

namespace App\Jobs;

use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Trip;
use App\Services\TripService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Hourly: owners often forget to press "complete". Trips that departed more
 * than GRACE_HOURS ago are completed automatically, so confirmed bookings
 * become COMPLETED (and ratable) and unanswered pending requests are rejected.
 */
class CompleteDepartedTrips implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const GRACE_HOURS = 6;

    public function handle(TripService $trips): void
    {
        $cutoff = now()->subHours(self::GRACE_HOURS);

        Trip::query()
            ->whereIn('status', [TripStatus::Published, TripStatus::Full, TripStatus::Started])
            // Coarse SQL filter on the date, exact check on date + time in PHP.
            ->whereDate('departure_date', '<=', $cutoff->toDateString())
            ->with('owner')
            ->get()
            ->filter(fn (Trip $trip) => $trip->departureAt()->lte($cutoff))
            ->each(function (Trip $trip) use ($trips) {
                try {
                    $trips->complete($trip);
                } catch (BusinessRuleException) {
                    // Status changed concurrently (e.g. owner completed it); nothing to do.
                }
            });
    }
}
