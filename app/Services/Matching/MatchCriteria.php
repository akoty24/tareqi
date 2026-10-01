<?php

namespace App\Services\Matching;

use App\Enums\CostType;
use App\Models\TripRequest;

/** What a passenger is looking for. Every field is optional. */
final class MatchCriteria
{
    public function __construct(
        public readonly ?string $origin = null,
        public readonly ?string $destination = null,
        public readonly ?string $date = null,
        public readonly ?string $timeFrom = null,
        public readonly ?string $timeTo = null,
        public readonly int $passengers = 1,
        public readonly ?CostType $costType = null,
        public readonly int $flexDays = 0,
    ) {
    }

    public static function fromArray(array $input): self
    {
        return new self(
            origin: $input['origin'] ?? null,
            destination: $input['destination'] ?? null,
            date: $input['date'] ?? null,
            timeFrom: $input['time_from'] ?? null,
            timeTo: $input['time_to'] ?? null,
            passengers: (int) ($input['passengers'] ?? 1),
            costType: isset($input['cost_type']) ? CostType::from($input['cost_type']) : null,
            flexDays: ! empty($input['flexible_dates']) ? 1 : 0,
        );
    }

    public static function fromTripRequest(TripRequest $request): self
    {
        return new self(
            origin: $request->origin,
            destination: $request->destination,
            date: $request->requested_date->toDateString(),
            timeFrom: $request->preferred_time_from,
            timeTo: $request->preferred_time_to,
            passengers: $request->passengers_count,
        );
    }
}
