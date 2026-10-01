<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Staff actions on user accounts; every change is recorded in the activity log. */
class UserModerationService
{
    public function __construct(
        private readonly TripService $trips,
        private readonly BookingService $bookings,
        private readonly ActivityLogger $activity,
    ) {
    }

    /**
     * Block a user: revoke every API token (immediate logout), then make sure
     * nobody relies on them any more — their upcoming trips are cancelled
     * (passengers are notified) and their active bookings are cancelled
     * (trip owners get their seats back and are notified).
     */
    public function block(User $user): User
    {
        DB::transaction(function () use ($user) {
            $user->forceFill(['status' => UserStatus::Blocked, 'blocked_at' => now()])->save();
            $user->tokens()->delete();
        });

        $user->trips()
            ->whereIn('status', [TripStatus::Draft, TripStatus::Published, TripStatus::Full])
            ->get()
            ->each(fn ($trip) => $this->quietly(fn () => $this->trips->cancel($trip, __('errors.account_blocked'), byAdmin: true)));

        $user->bookings()
            ->active()
            ->whereHas('trip', fn ($q) => $q->whereIn('status', [TripStatus::Published, TripStatus::Full]))
            ->get()
            ->each(fn ($booking) => $this->quietly(fn () => $this->bookings->cancel($booking, $user, 'account_blocked')));

        $this->activity->log('user.blocked', $user, ['name' => $user->name]);

        return $user;
    }

    public function unblock(User $user): User
    {
        $user->forceFill(['status' => UserStatus::Active, 'blocked_at' => null])->save();

        $this->activity->log('user.unblocked', $user, ['name' => $user->name]);

        return $user;
    }

    /** Correct account details; a changed email must be verified again unless stated otherwise. */
    public function update(User $user, array $data): User
    {
        $user->fill(collect($data)->only(['name', 'phone', 'email'])->all());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if (array_key_exists('email_verified', $data)) {
            $user->email_verified_at = $data['email_verified'] ? ($user->email_verified_at ?? now()) : null;
        }

        $changes = array_keys($user->getDirty());
        $user->save();

        if ($changes !== []) {
            $this->activity->log('user.updated', $user, ['name' => $user->name, 'fields' => $changes]);
        }

        return $user;
    }

    /**
     * Give a user a staff role, or remove it (null). Only a super admin may
     * grant the super admin role. Removing a role logs the user out so the
     * admin panel disappears right away.
     */
    public function assignRole(User $actor, User $user, ?Role $role): User
    {
        if ($role?->is_super && ! $actor->isSuperAdmin()) {
            throw BusinessRuleException::make('cannot_grant_super_admin', 403);
        }

        $previous = $user->role?->display_name;
        $user->role()->associate($role)->save();

        if ($role === null && $previous !== null) {
            $user->tokens()->delete();
        }

        $this->activity->log('user.role_changed', $user, [
            'name' => $user->name,
            'from' => $previous,
            'to' => $role?->display_name,
        ]);

        return $user->setRelation('role', $role);
    }

    /** Log the user out of every device. */
    public function revokeSessions(User $user): void
    {
        $user->tokens()->delete();
        $user->deviceTokens()->delete();

        $this->activity->log('user.sessions_revoked', $user, ['name' => $user->name]);
    }

    /** A trip/booking may have changed state meanwhile; skip it rather than fail the block. */
    private function quietly(callable $action): void
    {
        try {
            $action();
        } catch (BusinessRuleException) {
        }
    }
}
