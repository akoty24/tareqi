<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        $role = Role::factory()->withPermissions($permissions)->create();

        return $this->actingAsUser(User::factory()->withRole($role)->create());
    }

    public function test_regular_users_cannot_open_the_admin_panel(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->getJson('/api/admin/roles')->assertForbidden();
    }

    public function test_staff_only_reach_the_sections_their_role_allows(): void
    {
        $this->staff([Permission::DashboardView, Permission::ReportsView]);

        $this->getJson('/api/admin/dashboard')->assertOk();
        $this->getJson('/api/admin/reports')->assertOk();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->getJson('/api/admin/trips')->assertForbidden();
        $this->getJson('/api/admin/roles')->assertForbidden();
        $this->getJson('/api/admin/activity')->assertForbidden();
    }

    public function test_view_permission_does_not_allow_actions(): void
    {
        $this->staff([Permission::UsersView, Permission::TripsView]);
        $member = User::factory()->create();
        $trip = $this->publishedTrip();

        $this->getJson('/api/admin/users')->assertOk();
        $this->patchJson("/api/admin/users/{$member->id}/block")->assertForbidden();
        $this->putJson("/api/admin/users/{$member->id}", ['name' => 'اسم جديد'])->assertForbidden();
        $this->patchJson("/api/admin/trips/{$trip->id}/cancel")->assertForbidden();
    }

    public function test_me_exposes_role_and_effective_permissions(): void
    {
        $this->staff([Permission::UsersView]);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.permissions', ['users.view'])
            ->assertJsonPath('data.user.role.is_super', false);

        $this->actingAsAdmin();
        $this->getJson('/api/auth/me')
            ->assertJsonPath('data.user.role.is_super', true)
            ->assertJsonCount(count(Permission::cases()), 'data.user.permissions');
    }

    public function test_super_admin_manages_roles(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/permissions')->assertOk()->assertJsonPath('data.0.key', 'dashboard');

        $id = $this->postJson('/api/admin/roles', [
            'display_name' => 'مراجع البلاغات',
            'permissions' => ['reports.view', 'reports.manage'],
        ])->assertCreated()->assertJsonPath('data.permissions', ['reports.view', 'reports.manage'])->json('data.id');

        $this->putJson("/api/admin/roles/{$id}", ['permissions' => ['reports.view']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['reports.view']);

        $this->postJson('/api/admin/roles', ['display_name' => 'x', 'permissions' => ['not.a.permission']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['display_name', 'permissions.0']);

        $this->deleteJson("/api/admin/roles/{$id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $id]);
        $this->assertTrue(ActivityLog::where('action', 'role.deleted')->exists());
    }

    public function test_system_role_and_roles_in_use_are_protected(): void
    {
        $this->actingAsAdmin();
        $super = Role::superAdmin();
        $used = Role::factory()->withPermissions([Permission::UsersView])->create();
        User::factory()->withRole($used)->create();

        $this->putJson("/api/admin/roles/{$super->id}", ['permissions' => ['users.view']])
            ->assertStatus(409)->assertJsonPath('error_code', 'role_is_system');
        $this->deleteJson("/api/admin/roles/{$super->id}")->assertStatus(409);
        $this->deleteJson("/api/admin/roles/{$used->id}")
            ->assertStatus(409)->assertJsonPath('error_code', 'role_has_users');
    }

    public function test_assigning_and_removing_a_role(): void
    {
        $this->actingAsAdmin();
        $role = Role::factory()->withPermissions([Permission::ReportsView])->create();
        $member = User::factory()->create();
        $member->createToken('app');

        $this->patchJson("/api/admin/users/{$member->id}/role", ['role_id' => $role->id])
            ->assertOk()
            ->assertJsonPath('data.role.id', $role->id)
            ->assertJsonPath('data.permissions', ['reports.view']);
        $this->assertTrue($member->fresh()->hasPermission(Permission::ReportsView));

        // Back to a regular member: the admin panel disappears and sessions are revoked.
        $this->patchJson("/api/admin/users/{$member->id}/role", ['role_id' => null])
            ->assertOk()
            ->assertJsonPath('data.role', null);
        $this->assertFalse($member->fresh()->isAdmin());
        $this->assertSame(0, $member->tokens()->count());
        $this->assertSame(2, ActivityLog::where('action', 'user.role_changed')->count());
    }

    public function test_only_super_admin_can_grant_super_admin_or_touch_staff(): void
    {
        $this->staff([Permission::RolesManage, Permission::UsersBlock]);
        $member = User::factory()->create();
        $otherStaff = User::factory()->withRole(Role::factory()->create())->create();

        $this->patchJson("/api/admin/users/{$member->id}/role", ['role_id' => Role::superAdmin()->id])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'cannot_grant_super_admin');
        $this->patchJson("/api/admin/users/{$otherStaff->id}/block")->assertForbidden();
        $this->patchJson("/api/admin/users/{$otherStaff->id}/role", ['role_id' => null])->assertForbidden();
    }

    public function test_super_admins_cannot_be_blocked_but_can_be_demoted_by_another_super_admin(): void
    {
        $this->actingAsAdmin();
        $other = User::factory()->admin()->create();

        $this->patchJson("/api/admin/users/{$other->id}/block")->assertForbidden();
        $this->putJson("/api/admin/users/{$other->id}", ['name' => 'تعديل'])->assertForbidden();
        $this->patchJson("/api/admin/users/{$other->id}/role", ['role_id' => null])->assertOk();
        $this->assertSame(UserStatus::Active, $other->fresh()->status);
    }

    public function test_staff_with_permission_can_edit_a_member(): void
    {
        $this->staff([Permission::UsersView, Permission::UsersUpdate]);
        $member = User::factory()->unverified()->create();

        $this->putJson("/api/admin/users/{$member->id}", [
            'name' => 'محمد السيد',
            'phone' => '0101 234 5678',
            'email_verified' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'محمد السيد')
            ->assertJsonPath('data.phone', '01012345678')
            ->assertJsonPath('data.email_verified', true);

        $this->getJson("/api/admin/users/{$member->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'vehicles', 'stats' => ['trips', 'bookings', 'reports_against']]]);
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        $this->actingAsAdmin();
        $role = Role::factory()->create();
        User::factory()->withRole($role)->create();
        User::factory()->count(2)->create();

        $this->getJson('/api/admin/users?role=staff')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/users?role=member')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/admin/users?role={$role->id}")->assertOk()->assertJsonCount(1, 'data');
    }
}
