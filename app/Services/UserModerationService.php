<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserModerationService
{
    /** Block a user and revoke every API token so they are logged out immediately. */
    public function block(User $user): User
    {
        DB::transaction(function () use ($user) {
            $user->forceFill(['status' => UserStatus::Blocked, 'blocked_at' => now()])->save();
            $user->tokens()->delete();
        });

        return $user;
    }

    public function unblock(User $user): User
    {
        $user->forceFill(['status' => UserStatus::Active, 'blocked_at' => null])->save();

        return $user;
    }
}
