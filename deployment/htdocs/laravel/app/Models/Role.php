<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staff role. Community members have no role (users.role_id = null);
 * every user with a role can open the admin panel and do what its
 * permissions allow.
 */
class Role extends Model
{
    use HasFactory;

    public const SUPER_ADMIN = 'super-admin';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
            'is_super' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** The protected role with every permission (created on demand). */
    public static function superAdmin(): self
    {
        $role = static::query()->firstOrNew(['name' => self::SUPER_ADMIN]);
        if (! $role->exists) {
            $role->fill(['display_name' => 'مدير عام', 'description' => 'صلاحيات كاملة على المنصة.', 'permissions' => []]);
            $role->forceFill(['is_system' => true, 'is_super' => true])->save();
        }

        return $role;
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return $this->is_super || in_array($value, $this->permissions ?? [], true);
    }

    /** Effective permissions (all of them for the super admin). */
    public function effectivePermissions(): array
    {
        return $this->is_super
            ? Permission::values()
            : array_values(array_intersect(Permission::values(), $this->permissions ?? []));
    }

    public function scopeEditable(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }
}
