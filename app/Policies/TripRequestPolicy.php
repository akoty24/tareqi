<?php

namespace App\Policies;

use App\Models\TripRequest;
use App\Models\User;

class TripRequestPolicy
{
    public function view(User $user, TripRequest $tripRequest): bool
    {
        return $tripRequest->user_id === $user->id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, TripRequest $tripRequest): bool
    {
        return $user->isActive() && $tripRequest->user_id === $user->id;
    }

    public function delete(User $user, TripRequest $tripRequest): bool
    {
        return $this->update($user, $tripRequest);
    }
}
