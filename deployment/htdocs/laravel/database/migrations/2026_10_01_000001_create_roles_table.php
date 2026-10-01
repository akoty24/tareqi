<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff roles with configurable permissions (values of App\Enums\Permission).
 *
 * users.role (user|admin) is replaced by users.role_id: regular community
 * members have no role, staff members have exactly one. The "super-admin"
 * role is a protected system role that implicitly has every permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->string('description', 255)->nullable();
            $table->json('permissions');
            // System roles cannot be renamed, edited or deleted.
            $table->boolean('is_system')->default(false);
            // The super admin role bypasses the permissions list.
            $table->boolean('is_super')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('profile_photo_path')->constrained('roles')->restrictOnDelete();
        });

        // Existing admins become super admins.
        if (DB::table('users')->where('role', 'admin')->exists()) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'super-admin',
                'display_name' => 'مدير عام',
                'description' => 'صلاحيات كاملة على المنصة.',
                'permissions' => '[]',
                'is_system' => true,
                'is_super' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('users')->where('role', 'admin')->update(['role_id' => $roleId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('profile_photo_path');
        });
        DB::table('users')->whereNotNull('role_id')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('roles');
    }
};
