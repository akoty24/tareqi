<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RatingPolicy
{
    /**
     * A participant (passenger or trip owner) of a completed booking may rate
     * the other party once. Nobody can rate themselves.
     */
    public function create(User $user, Booking $booking): Response
    {
        $booking->loadMissing('trip');
        $isPassenger = $booking->isPassenger($user);
        $isOwner = $booking->trip->owner_id === $user->id;

        if (! $user->isActive() || (! $isPassenger && ! $isOwner)) {
            return Response::deny(__('errors.not_booking_participant'));
        }
        if ($isPassenger && $isOwner) {
            return Response::deny(__('errors.forbidden'));
        }
        if ($booking->status !== BookingStatus::Completed) {
            return Response::deny(__('ratings.not_completed'));
        }
        if ($booking->ratings()->where('rater_id', $user->id)->exists()) {
            return Response::deny(__('errors.already_rated'));
        }

        return Response::allow();
    }
}
