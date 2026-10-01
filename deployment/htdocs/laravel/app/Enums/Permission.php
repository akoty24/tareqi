<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Everything a staff member can be allowed to do in the admin panel.
 * Roles store a list of these values; each one is also registered as a Gate
 * ability (AppServiceProvider) so routes can use `can:users.block` etc.
 */
enum Permission: string
{
    use HasValues;

    case DashboardView = 'dashboard.view';

    case UsersView = 'users.view';
    case UsersUpdate = 'users.update';
    case UsersBlock = 'users.block';

    case RolesManage = 'roles.manage';

    case TripsView = 'trips.view';
    case TripsManage = 'trips.manage';

    case BookingsView = 'bookings.view';
    case BookingsManage = 'bookings.manage';

    case TripRequestsView = 'trip_requests.view';
    case TripRequestsManage = 'trip_requests.manage';

    case VehiclesView = 'vehicles.view';
    case VehiclesManage = 'vehicles.manage';

    case RatingsView = 'ratings.view';
    case RatingsManage = 'ratings.manage';

    case ReportsView = 'reports.view';
    case ReportsManage = 'reports.manage';

    case NotificationsSend = 'notifications.send';

    case ActivityView = 'activity.view';

    /** Section the permission belongs to (used to group checkboxes in the UI). */
    public function group(): string
    {
        return strtok($this->value, '.');
    }

    public function label(): string
    {
        return __('permissions.'.str_replace('.', '_', $this->value));
    }

    /** @return array<int, array{key: string, label: string, permissions: array<int, array{value: string, label: string}>}> */
    public static function grouped(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $p) => $p->group())
            ->map(fn ($items, $group) => [
                'key' => $group,
                'label' => __("permissions.groups.{$group}"),
                'permissions' => $items->map(fn (self $p) => ['value' => $p->value, 'label' => $p->label()])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
