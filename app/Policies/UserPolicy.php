<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Admins can block regular users, never themselves or other admins. */
    public function block(User $user, User $target): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny(__('errors.forbidden'));
        }
        if ($target->isAdmin() || $target->is($user)) {
            return Response::deny(__('errors.cannot_moderate_admin'));
        }

        return Response::allow();
    }

    public function unblock(User $user, User $target): Response
    {
        return $this->block($user, $target);
    }
}
