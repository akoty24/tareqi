<?php

namespace App\Services\Matching;

use App\Models\Trip;
use App\Models\User;
use App\Support\PlaceName;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Deterministic trip matching (no AI).
 *
 *  Criterion          Points  How it is calculated
 *  -----------------  ------  --------------------------------------------------------
 *  Origin             30      normalized names equal = 30; one contains the other = 15
 *  Destination        30      same rule as origin
 *  Date               20      same day = 20; +/- 1 day = 10; otherwise 0
 *  Time proximity     15      inside the requested window = 15; otherwise decreases
 *                             linearly to 0 at 3 hours away from the window
 *  Availability        5      available_seats >= requested passengers
 *
 * A criterion the passenger did not specify awards its full points.
 *
 * Filtering is done in SQL (indexed columns, bookable trips only); scoring is
 * done here on the bounded candidate set so the rules are identical on every
 * database. The class is resolved from the container, so a smarter matcher
 * can replace it by binding a subclass in AppServiceProvider.
 */
class TripMatchingService
{
    public const ORIGIN_POINTS = 30;
    public const DESTINATION_POINTS = 30;
    public const DATE_POINTS = 20;
    public const TIME_POINTS = 15;
    public const AVAILABILITY_POINTS = 5;

    /** Distance (minutes) outside the preferred window at which time points reach 0. */
    public const TIME_DECAY_MINUTES = 180;

    /** Upper bound of candidates scored per search. */
    public const CANDIDATE_LIMIT = 300;

    /** Search bookable trips and return them ordered by score (desc), paginated. */
    public function search(MatchCriteria $criteria, ?User $viewer = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $candidates = Trip::query()
            ->bookable($criteria->passengers)
            ->fromPlace($criteria->origin)
            ->toPlace($criteria->destination)
            ->onDate($criteria->date, $criteria->flexDays)
            ->departingBetween($criteria->timeFrom, $criteria->timeTo)
            ->when($criteria->costType, fn ($q, $type) => $q->where('cost_type', $type))
            ->when($viewer, fn ($q) => $q->where('owner_id', '!=', $viewer->id))
            ->with(['owner', 'vehicle'])
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $ranked = $candidates
            ->each(fn (Trip $trip) => $trip->match = $this->score($trip, $criteria))
            ->sortByDesc(fn (Trip $trip) => $trip->match['score'])
            ->values();

        return new LengthAwarePaginator(
            $ranked->forPage($page, $perPage)->values(),
            $ranked->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /** @return array{score:int, breakdown:array<string,int>} */
    public function score(Trip $trip, MatchCriteria $criteria): array
    {
        $breakdown = [
            'origin' => $this->placeScore($trip->origin_normalized, $criteria->origin, self::ORIGIN_POINTS),
            'destination' => $this->placeScore($trip->destination_normalized, $criteria->destination, self::DESTINATION_POINTS),
            'date' => $this->dateScore($trip, $criteria->date),
            'time' => $this->timeScore($trip->departure_time, $criteria->timeFrom, $criteria->timeTo),
            'availability' => $trip->available_seats >= $criteria->passengers ? self::AVAILABILITY_POINTS : 0,
        ];

        return ['score' => array_sum($breakdown), 'breakdown' => $breakdown];
    }

    private function placeScore(string $tripPlace, ?string $wanted, int $points): int
    {
        if (blank($wanted)) {
            return $points;
        }
        $wanted = PlaceName::normalize($wanted);

        if ($tripPlace === $wanted) {
            return $points;
        }

        return str_contains($tripPlace, $wanted) || str_contains($wanted, $tripPlace)
            ? intdiv($points, 2)
            : 0;
    }

    private function dateScore(Trip $trip, ?string $wanted): int
    {
        if (blank($wanted)) {
            return self::DATE_POINTS;
        }
        $diff = abs($trip->departure_date->startOfDay()->diffInDays(Carbon::parse($wanted)->startOfDay()));

        return match (true) {
            $diff < 1 => self::DATE_POINTS,
            $diff < 2 => intdiv(self::DATE_POINTS, 2),
            default => 0,
        };
    }

    private function timeScore(string $departureTime, ?string $from, ?string $to): int
    {
        if (blank($from) && blank($to)) {
            return self::TIME_POINTS;
        }
        $minutes = fn (string $time) => Carbon::parse($time)->hour * 60 + Carbon::parse($time)->minute;

        $trip = $minutes($departureTime);
        $start = $minutes($from ?? $to);
        $end = $minutes($to ?? $from);
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $distance = $trip < $start ? $start - $trip : max(0, $trip - $end);
        if ($distance === 0) {
            return self::TIME_POINTS;
        }

        return (int) round(self::TIME_POINTS * max(0, 1 - $distance / self::TIME_DECAY_MINUTES));
    }
}
