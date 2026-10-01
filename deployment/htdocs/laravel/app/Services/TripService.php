<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CostType;
use App\Enums\TripStatus;
use App\Events\BookingStatusChanged;
use App\Events\TripCancelled;
use App\Events\TripPublished;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TripService
{
    /** Route/time fields that cannot change once passengers have booked. */
    private const LOCKED_WHEN_BOOKED = ['origin', 'destination', 'departure_date', 'departure_time'];

    /**
     * Create a trip and, optionally, its return trip in one transaction.
     *
     * @param  array  $data  validated data; may contain `publish` (bool) and `return_trip` (array)
     */
    public function create(User $owner, array $data): Trip
    {
        $returnData = Arr::pull($data, 'return_trip');
        $publish = (bool) Arr::pull($data, 'publish', true);
        $status = $publish ? TripStatus::Published : TripStatus::Draft;

        [$trip, $return] = DB::transaction(function () use ($owner, $data, $returnData, $status) {
            $trip = $this->persistNew($owner, $data, $status);
            $return = $returnData ? $this->persistReturn($trip, $returnData) : null;

            return [$trip, $return];
        });

        if ($publish) {
            TripPublished::dispatch($trip);
            $return && TripPublished::dispatch($return);
        }

        return $trip->load(['vehicle', 'returnTrip']);
    }

    /** Add a return trip to an existing outbound trip. */
    public function addReturnTrip(Trip $outbound, array $data): Trip
    {
        if ($outbound->isReturnTrip()) {
            throw BusinessRuleException::make('return_of_return_trip');
        }
        if ($outbound->returnTrip()->exists()) {
            throw BusinessRuleException::make('return_trip_exists');
        }
        if (! $outbound->status->isEditable()) {
            throw BusinessRuleException::make('trip_not_editable');
        }

        $return = DB::transaction(fn () => $this->persistReturn($outbound, $data));

        if ($return->status === TripStatus::Published) {
            TripPublished::dispatch($return);
        }

        return $return;
    }

    public function update(Trip $trip, array $data): Trip
    {
        return DB::transaction(function () use ($trip, $data) {
            /** @var Trip $trip */
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);

            if (! $trip->status->isEditable()) {
                throw BusinessRuleException::make('trip_not_editable');
            }

            $trip->fill(Arr::except($data, ['total_seats']));

            if ($trip->isDirty(self::LOCKED_WHEN_BOOKED) && $trip->activeBookings()->exists()) {
                throw BusinessRuleException::make('trip_has_bookings');
            }

            if (array_key_exists('total_seats', $data)) {
                $booked = $trip->bookedSeats();
                if ($data['total_seats'] < $booked) {
                    throw BusinessRuleException::make('seats_below_booked', 422, ['booked' => $booked]);
                }
                $trip->total_seats = $data['total_seats'];
                $trip->available_seats = $data['total_seats'] - $booked;
                $this->syncFullStatus($trip);
            }

            $this->normalizePricing($trip);
            $this->assertReturnAfterOutbound($trip);
            $trip->save();

            return $trip;
        });
    }

    public function publish(Trip $trip): Trip
    {
        if ($trip->status !== TripStatus::Draft) {
            throw BusinessRuleException::make('trip_not_draft');
        }
        if ($trip->hasDeparted()) {
            throw BusinessRuleException::make('trip_in_past');
        }

        $trip->status = TripStatus::Published;
        $this->syncFullStatus($trip);
        $trip->save();

        TripPublished::dispatch($trip);

        return $trip;
    }

    /**
     * Cancel a trip and every active booking on it. Return trips are
     * independent and are not cancelled automatically.
     */
    public function cancel(Trip $trip, ?string $reason = null, bool $byAdmin = false): Trip
    {
        [$trip, $cancelled] = DB::transaction(function () use ($trip, $reason) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);

            if ($trip->status->isFinished() || $trip->status === TripStatus::Started) {
                throw BusinessRuleException::make('trip_cannot_be_cancelled');
            }

            $bookings = $trip->activeBookings()->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $booking->status = BookingStatus::Cancelled;
                $booking->cancelled_at = now();
                $booking->cancellation_reason = 'trip_cancelled';
                $booking->save();
            }

            $trip->status = TripStatus::Cancelled;
            $trip->cancelled_at = now();
            $trip->cancellation_reason = $reason;
            $trip->available_seats = $trip->total_seats;
            $trip->save();

            return [$trip, $bookings];
        });

        TripCancelled::dispatch($trip, $cancelled, $byAdmin);

        return $trip;
    }

    /** Owner marks the trip as on the road. Pending requests are rejected. */
    public function start(Trip $trip): Trip
    {
        if (! in_array($trip->status, [TripStatus::Published, TripStatus::Full], true)) {
            throw BusinessRuleException::make('trip_cannot_start');
        }
        if ($trip->departureAt()->subHours(2)->isFuture()) {
            throw BusinessRuleException::make('trip_too_early_to_start');
        }

        $rejected = DB::transaction(function () use ($trip) {
            $rejected = $this->rejectPending($trip);
            $trip->status = TripStatus::Started;
            $trip->save();

            return $rejected;
        });

        $this->dispatchStatusChanges($rejected, BookingStatus::Pending, $trip->owner);

        return $trip;
    }

    /** Completes the trip; confirmed bookings become completed (and ratable). */
    public function complete(Trip $trip): Trip
    {
        $allowed = $trip->status === TripStatus::Started
            || (in_array($trip->status, [TripStatus::Published, TripStatus::Full], true) && $trip->hasDeparted());

        if (! $allowed) {
            throw BusinessRuleException::make('trip_cannot_complete');
        }

        $rejected = DB::transaction(function () use ($trip) {
            $rejected = $this->rejectPending($trip);
            $trip->bookings()->where('status', BookingStatus::Confirmed)
                ->update(['status' => BookingStatus::Completed, 'updated_at' => now()]);
            $trip->status = TripStatus::Completed;
            $trip->save();

            return $rejected;
        });

        $this->dispatchStatusChanges($rejected, BookingStatus::Pending, $trip->owner);

        return $trip;
    }

    /** Hard delete is only allowed for trips nobody has booked; otherwise cancel. */
    public function delete(Trip $trip): void
    {
        if ($trip->bookings()->exists()) {
            throw BusinessRuleException::make('trip_has_bookings_delete', 409);
        }

        $trip->delete();
    }

    // ---- internals -----------------------------------------------------

    private function persistNew(User $owner, array $data, TripStatus $status, ?Trip $parent = null): Trip
    {
        $trip = new Trip($data);
        $trip->owner_id = $owner->id;
        $trip->parent_trip_id = $parent?->id;
        $trip->available_seats = $trip->total_seats;
        $trip->status = $status;
        $trip->setRelation('parentTrip', $parent);

        $this->normalizePricing($trip);
        if ($trip->hasDeparted()) {
            throw BusinessRuleException::make('trip_in_past');
        }
        $this->assertReturnAfterOutbound($trip);
        $trip->save();

        return $trip;
    }

    /** Return trip: reversed route, same vehicle; pricing/seats default to the outbound trip. */
    private function persistReturn(Trip $outbound, array $data): Trip
    {
        $defaults = $outbound->only([
            'vehicle_id', 'total_seats', 'cost_type', 'price_per_seat',
            'estimated_cost_per_passenger', 'auto_confirm_bookings',
        ]);
        $data = array_merge($defaults, array_filter($data, fn ($v) => $v !== null), [
            'origin' => $outbound->destination,
            'destination' => $outbound->origin,
        ]);

        $status = $outbound->status === TripStatus::Draft ? TripStatus::Draft : TripStatus::Published;

        return $this->persistNew($outbound->owner, $data, $status, $outbound);
    }

    /** Enforce the pricing model: only the field relevant to the cost type is kept. */
    private function normalizePricing(Trip $trip): void
    {
        switch ($trip->cost_type) {
            case CostType::Free:
                $trip->price_per_seat = null;
                $trip->estimated_cost_per_passenger = null;
                break;
            case CostType::CostSharing:
                $trip->price_per_seat = null;
                if ($trip->estimated_cost_per_passenger === null) {
                    throw BusinessRuleException::make('estimated_cost_required');
                }
                break;
            case CostType::FixedPrice:
                $trip->estimated_cost_per_passenger = null;
                if ($trip->price_per_seat === null) {
                    throw BusinessRuleException::make('price_required');
                }
                break;
        }
    }

    private function assertReturnAfterOutbound(Trip $trip): void
    {
        if ($trip->parent_trip_id) {
            $parent = $trip->parentTrip;
            if ($parent && $trip->departureAt()->lte($parent->departureAt())) {
                throw BusinessRuleException::make('return_before_outbound');
            }
        }

        $return = $trip->exists ? $trip->returnTrip : null;
        if ($return && $return->departureAt()->lte($trip->departureAt())) {
            throw BusinessRuleException::make('return_before_outbound');
        }
    }

    private function syncFullStatus(Trip $trip): void
    {
        if ($trip->status === TripStatus::Published && $trip->available_seats === 0) {
            $trip->status = TripStatus::Full;
        } elseif ($trip->status === TripStatus::Full && $trip->available_seats > 0) {
            $trip->status = TripStatus::Published;
        }
    }

    /** @return Collection<int, Booking> */
    private function rejectPending(Trip $trip): Collection
    {
        $pending = $trip->bookings()->where('status', BookingStatus::Pending)->lockForUpdate()->get();

        foreach ($pending as $booking) {
            $booking->status = BookingStatus::Rejected;
            $booking->save();
            $trip->releaseSeats($booking->seats);
        }

        return $pending;
    }

    private function dispatchStatusChanges(Collection $bookings, BookingStatus $from, ?User $actor): void
    {
        foreach ($bookings as $booking) {
            BookingStatusChanged::dispatch($booking, $from, $actor);
        }
    }
}
