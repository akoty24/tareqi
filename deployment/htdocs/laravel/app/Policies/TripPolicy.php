<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    /**
     * Open trips are public (guests can browse before signing up). Drafts and
     * cancelled trips are only visible to the owner, past passengers and staff.
     */
    public function view(?User $user, Trip $trip): bool
    {
        if (in_array($trip->status, TripStatus::visible(), true)) {
            return true;
        }
        if ($user === null) {
            return false;
        }

        return $trip->isOwnedBy($user)
            || $user->hasPermission(Permission::TripsView)
            || $trip->bookings()->where('passenger_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /** Covers edit, publish, cancel, start, complete and adding a return trip. */
    public function update(User $user, Trip $trip): bool
    {
        return $user->isActive() && $trip->isOwnedBy($user);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $this->update($user, $trip);
    }

    public function book(User $user, Trip $trip): bool
    {
        return $user->isActive() && ! $trip->isOwnedBy($user);
    }

    /** Admin moderation (e.g. cancelling an inappropriate trip). */
    public function moderate(User $user, Trip $trip): bool
    {
        return $user->hasPermission(Permission::TripsManage);
    }
}
