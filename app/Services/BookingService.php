<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\TripRequestStatus;
use App\Enums\TripStatus;
use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * All seat changes happen here, inside a transaction that holds a row lock
 * (SELECT ... FOR UPDATE) on the trip. Two passengers booking the last seats
 * at the same time are serialized by that lock, and the second one sees the
 * already-decremented `available_seats`.
 *
 * Lock order is always trip -> booking to avoid deadlocks.
 */
class BookingService
{
    public function create(Trip $trip, User $passenger, int $seats, ?string $notes = null): Booking
    {
        if ($seats < 1) {
            throw BusinessRuleException::make('invalid_seats');
        }

        $booking = DB::transaction(function () use ($trip, $passenger, $seats, $notes) {
            // Re-read the trip under lock: the instance passed in may be stale.
            /** @var Trip $trip */
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);

            if ($trip->owner_id === $passenger->id) {
                throw BusinessRuleException::make('cannot_book_own_trip', 403);
            }
            $this->assertTripBookable($trip);

            if ($seats > $trip->available_seats) {
                throw BusinessRuleException::make('insufficient_seats', 422, ['available' => $trip->available_seats]);
            }

            $alreadyBooked = $trip->bookings()
                ->where('passenger_id', $passenger->id)
                ->active()
                ->exists();
            if ($alreadyBooked) {
                throw BusinessRuleException::make('already_booked', 409);
            }

            $pricePerSeat = $trip->currentSeatPrice();

            $booking = new Booking(['seats' => $seats, 'notes' => $notes]);
            $booking->trip_id = $trip->id;
            $booking->passenger_id = $passenger->id;
            // Price snapshot: stored now, never derived from the trip again.
            $booking->price_per_seat = $pricePerSeat;
            $booking->total_price = number_format((float) $pricePerSeat * $seats, 2, '.', '');

            if ($trip->auto_confirm_bookings) {
                $booking->status = BookingStatus::Confirmed;
                $booking->confirmed_at = now();
            } else {
                $booking->status = BookingStatus::Pending;
            }
            $booking->save();

            $trip->reserveSeats($seats);
            $trip->save();

            $this->fulfillMatchingRequests($trip, $passenger);

            return $booking->setRelation('trip', $trip);
        });

        BookingCreated::dispatch($booking);

        return $booking;
    }

    public function confirm(Booking $booking, User $actor): Booking
    {
        return $this->transition($booking, $actor, function (Booking $booking, Trip $trip) {
            if ($booking->status !== BookingStatus::Pending) {
                throw BusinessRuleException::make('booking_not_pending');
            }
            if (! in_array($trip->status, [TripStatus::Published, TripStatus::Full], true)) {
                throw BusinessRuleException::make('trip_not_bookable');
            }
            $booking->status = BookingStatus::Confirmed;
            $booking->confirmed_at = now();
        });
    }

    public function reject(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return $this->transition($booking, $actor, function (Booking $booking, Trip $trip) use ($reason) {
            if ($booking->status !== BookingStatus::Pending) {
                throw BusinessRuleException::make('booking_not_pending');
            }
            $booking->status = BookingStatus::Rejected;
            $booking->cancellation_reason = $reason;
            $trip->releaseSeats($booking->seats);
        });
    }

    /** Cancellation by the passenger or by the trip owner, before the trip starts. */
    public function cancel(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return $this->transition($booking, $actor, function (Booking $booking, Trip $trip) use ($reason) {
            if (! $booking->status->isActive()) {
                throw BusinessRuleException::make('booking_not_active');
            }
            if (in_array($trip->status, [TripStatus::Started, TripStatus::Completed], true)) {
                throw BusinessRuleException::make('trip_already_started');
            }
            $booking->status = BookingStatus::Cancelled;
            $booking->cancelled_at = now();
            $booking->cancellation_reason = $reason;
            $trip->releaseSeats($booking->seats);
        });
    }

    /**
     * Lock trip then booking, apply $change, persist both, fire the event after commit.
     *
     * @param  callable(Booking, Trip): void  $change
     */
    private function transition(Booking $booking, User $actor, callable $change): Booking
    {
        [$booking, $previous] = DB::transaction(function () use ($booking, $change) {
            /** @var Trip $trip */
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $previous = $booking->status;

            $change($booking, $trip);

            $booking->save();
            $trip->save();

            return [$booking->setRelation('trip', $trip), $previous];
        });

        BookingStatusChanged::dispatch($booking, $previous, $actor);

        return $booking;
    }

    private function assertTripBookable(Trip $trip): void
    {
        if (! $trip->status->acceptsBookings()) {
            throw BusinessRuleException::make(
                $trip->status === TripStatus::Full ? 'trip_full' : 'trip_not_bookable'
            );
        }
        if ($trip->hasDeparted()) {
            throw BusinessRuleException::make('trip_in_past');
        }
    }

    /** A passenger booking a trip they were matched with fulfills that request. */
    private function fulfillMatchingRequests(Trip $trip, User $passenger): void
    {
        TripRequest::query()
            ->where('user_id', $passenger->id)
            ->active()
            ->whereHas('matchedTrips', fn ($q) => $q->where('trips.id', $trip->id))
            ->update(['status' => TripRequestStatus::Fulfilled, 'updated_at' => now()]);
    }
}
