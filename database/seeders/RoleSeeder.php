<?php

namespace Database\Seeders;

use App\Enums\Permission as P;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Default staff roles. The super admin role is a protected system role;
 * the others are ordinary roles the super admin can edit or delete.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::superAdmin();

        Role::query()->updateOrCreate(['name' => 'moderator'], [
            'display_name' => 'مشرف',
            'description' => 'متابعة البلاغات والرحلات والحجوزات وإيقاف المخالفين.',
            'permissions' => array_map(fn (P $p) => $p->value, [
                P::DashboardView,
                P::UsersView, P::UsersBlock,
                P::TripsView, P::TripsManage,
                P::BookingsView, P::BookingsManage,
                P::TripRequestsView, P::TripRequestsManage,
                P::VehiclesView,
                P::RatingsView, P::RatingsManage,
                P::ReportsView, P::ReportsManage,
            ]),
        ]);

        Role::query()->updateOrCreate(['name' => 'support'], [
            'display_name' => 'خدمة العملاء',
            'description' => 'عرض البيانات للرد على استفسارات المستخدمين وإرسال الإشعارات.',
            'permissions' => array_map(fn (P $p) => $p->value, [
                P::DashboardView,
                P::UsersView, P::UsersUpdate,
                P::TripsView, P::BookingsView, P::TripRequestsView,
                P::VehiclesView, P::RatingsView, P::ReportsView,
                P::NotificationsSend,
            ]),
        ]);
    }
}
