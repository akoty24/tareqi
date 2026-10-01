<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fixed demo accounts (documented in README) + random community members.
 * All passwords are "password". Runs after RoleSeeder.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'مدير المنصة',
            'email' => 'admin@mishwar.test',
            'phone' => '01000000000',
        ]);
        User::factory()->withRole(Role::where('name', 'moderator')->firstOrFail())->create([
            'name' => 'مشرف المنصة',
            'email' => 'moderator@mishwar.test',
            'phone' => '01000000004',
        ]);
        User::factory()->withRole(Role::where('name', 'support')->firstOrFail())->create([
            'name' => 'خدمة العملاء',
            'email' => 'support@mishwar.test',
            'phone' => '01000000005',
        ]);
        User::factory()->create([
            'name' => 'أحمد محمود الشافعي',
            'email' => 'driver@mishwar.test',
            'phone' => '01000000001',
        ]);
        User::factory()->create([
            'name' => 'سارة إبراهيم منصور',
            'email' => 'passenger@mishwar.test',
            'phone' => '01000000002',
        ]);
        User::factory()->blocked()->create([
            'name' => 'حساب موقوف',
            'email' => 'blocked@mishwar.test',
            'phone' => '01000000003',
        ]);
        User::factory()->count(12)->create();
    }
}
