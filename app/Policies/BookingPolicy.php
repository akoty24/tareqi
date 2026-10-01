<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $booking->isPassenger($user) || $this->ownsTrip($user, $booking) || $user->hasPermission(Permission::BookingsView);
    }

    /** Only the trip owner approves or rejects. */
    public function confirm(User $user, Booking $booking): bool
    {
        return $user->isActive() && $this->ownsTrip($user, $booking);
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $this->confirm($user, $booking);
    }

    /** The passenger or the trip owner may cancel. */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->isActive() && ($booking->isPassenger($user) || $this->ownsTrip($user, $booking));
    }

    /** Staff cancellation (e.g. a fraudulent booking). */
    public function moderate(User $user, Booking $booking): bool
    {
        return $user->hasPermission(Permission::BookingsManage);
    }

    private function ownsTrip(User $user, Booking $booking): bool
    {
        return $booking->trip()->where('owner_id', $user->id)->exists();
    }
}
