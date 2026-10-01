<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Staff actions on accounts. Nobody can moderate themselves, and other
 * staff members can only be moderated by a super admin.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::UsersView);
    }

    public function update(User $user, User $target): Response
    {
        return $this->moderate($user, $target, Permission::UsersUpdate);
    }

    public function block(User $user, User $target): Response
    {
        return $this->moderate($user, $target, Permission::UsersBlock);
    }

    public function unblock(User $user, User $target): Response
    {
        return $this->block($user, $target);
    }

    /** Assigning/removing a role; the super admin role can only be granted by a super admin. */
    public function assignRole(User $user, User $target): Response
    {
        return $this->moderate($user, $target, Permission::RolesManage, superAdminTarget: true);
    }

    /**
     * Super admin accounts can never be edited or blocked; another super
     * admin may only change their role (e.g. to demote them).
     */
    private function moderate(User $user, User $target, Permission $permission, bool $superAdminTarget = false): Response
    {
        if (! $user->hasPermission($permission)) {
            return Response::deny(__('errors.forbidden'));
        }
        if ($target->is($user)) {
            return Response::deny(__('errors.cannot_moderate_self'));
        }
        if ($target->isSuperAdmin() && ! $superAdminTarget) {
            return Response::deny(__('errors.cannot_moderate_super_admin'));
        }
        if ($target->isAdmin() && ! $user->isSuperAdmin()) {
            return Response::deny(__('errors.cannot_moderate_admin'));
        }

        return Response::allow();
    }
}
