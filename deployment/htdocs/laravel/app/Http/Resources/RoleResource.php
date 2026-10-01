<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_super' => $this->is_super,
            'permissions' => $this->effectivePermissions(),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
