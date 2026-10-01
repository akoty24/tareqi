<?php

namespace Database\Factories;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = 'role-'.Str::lower(Str::random(8));

        return [
            'name' => $name,
            'display_name' => 'دور '.$name,
            'description' => null,
            'permissions' => [],
        ];
    }

    /** @param  list<Permission|string>  $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn () => [
            'permissions' => array_map(fn ($p) => $p instanceof Permission ? $p->value : $p, $permissions),
        ]);
    }
}
