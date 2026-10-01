<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isActive() && $vehicle->user_id === $user->id;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }

    public function moderate(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermission(Permission::VehiclesManage);
    }
}
