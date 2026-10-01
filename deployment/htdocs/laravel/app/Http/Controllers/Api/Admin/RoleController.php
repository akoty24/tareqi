<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Permission;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity)
    {
    }

    public function index(): JsonResponse
    {
        $roles = Role::query()->withCount('users')->orderByDesc('is_super')->orderBy('id')->get();

        return $this->success(RoleResource::collection($roles), __('messages.ok'));
    }

    /** Every assignable permission, grouped by section, with translated labels. */
    public function permissions(): JsonResponse
    {
        return $this->success(Permission::grouped(), __('messages.ok'));
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = new Role($request->validated());
        $role->name = $this->uniqueName($request->input('display_name'));
        $role->save();

        $this->activity->log('role.created', $role, ['name' => $role->display_name]);

        return $this->created(new RoleResource($role->loadCount('users')), __('messages.role_created'));
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $this->ensureEditable($role);

        $role->update($request->validated());

        $this->activity->log('role.updated', $role, ['name' => $role->display_name]);

        return $this->updated(new RoleResource($role->loadCount('users')), __('messages.role_updated'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->ensureEditable($role);
        if ($role->users()->exists()) {
            throw BusinessRuleException::make('role_has_users', 409);
        }

        $role->delete();

        $this->activity->log('role.deleted', null, ['name' => $role->display_name]);

        return $this->deleted(__('messages.role_deleted'));
    }

    private function ensureEditable(Role $role): void
    {
        if ($role->is_system) {
            throw BusinessRuleException::make('role_is_system', 409);
        }
    }

    /** Machine name derived from the display name (Arabic names fall back to a random slug). */
    private function uniqueName(string $displayName): string
    {
        $base = Str::slug($displayName) ?: 'role-'.Str::lower(Str::random(6));
        $name = $base;
        for ($i = 2; Role::query()->where('name', $name)->exists(); $i++) {
            $name = "{$base}-{$i}";
        }

        return $name;
    }
}
