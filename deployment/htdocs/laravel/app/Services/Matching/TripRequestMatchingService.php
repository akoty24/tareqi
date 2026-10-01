<?php

namespace App\Services\Matching;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripRequest;
use App\Notifications\TripRequestMatchedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Connects trip requests ("I need a ride") with published trips.
 * Matching never creates bookings: the passenger decides.
 */
class TripRequestMatchingService
{
    /** Minimum TripMatchingService score to count as a match. */
    public const THRESHOLD = 70;

    public function __construct(private readonly TripMatchingService $matcher)
    {
    }

    /**
     * Called when a trip is published: notify owners of matching active
     * requests. Each request/trip pair is notified at most once (unique key
     * on trip_request_matches).
     *
     * @return int number of requests notified
     */
    public function matchTrip(Trip $trip): int
    {
        $trip->refresh();
        if ($trip->status !== TripStatus::Published) {
            return 0;
        }

        $requests = TripRequest::query()
            ->active()
            ->whereDate('requested_date', $trip->departure_date->toDateString())
            ->where('user_id', '!=', $trip->owner_id)
            ->where('passengers_count', '<=', $trip->available_seats)
            ->with('user')
            ->get();

        $notified = 0;
        foreach ($requests as $request) {
            $result = $this->matcher->score($trip, MatchCriteria::fromTripRequest($request));
            if ($result['score'] < self::THRESHOLD) {
                continue;
            }

            $inserted = DB::table('trip_request_matches')->insertOrIgnore([
                'trip_request_id' => $request->id,
                'trip_id' => $trip->id,
                'score' => $result['score'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted > 0 && $request->user->isActive()) {
                $request->user->notify(new TripRequestMatchedNotification($request, $trip, $result['score']));
                $notified++;
            }
        }

        return $notified;
    }

    /** Currently bookable trips that match a request, best first. */
    public function suggestionsFor(TripRequest $request, int $limit = 10): Collection
    {
        return $this->matcher
            ->search(MatchCriteria::fromTripRequest($request), $request->user, $limit)
            ->getCollection()
            ->filter(fn (Trip $trip) => $trip->match['score'] >= self::THRESHOLD)
            ->values();
    }
}
