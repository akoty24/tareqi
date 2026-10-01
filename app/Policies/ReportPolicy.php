<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ReportsView);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->hasPermission(Permission::ReportsView);
    }

    public function update(User $user, Report $report): bool
    {
        return $user->hasPermission(Permission::ReportsManage);
    }
}
