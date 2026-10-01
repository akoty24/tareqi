<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly UserModerationService $moderation)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ]);

        $users = User::query()
            ->withCompletedTripCounts()
            ->search($request->input('search'))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('role'), fn ($q, $role) => $q->where('role', $role))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($users, __('messages.ok'), UserResource::class);
    }

    public function block(User $user): JsonResponse
    {
        $this->authorize('block', $user);

        return $this->updated(new UserResource($this->moderation->block($user)), __('messages.user_blocked'));
    }

    public function unblock(User $user): JsonResponse
    {
        $this->authorize('unblock', $user);

        return $this->updated(new UserResource($this->moderation->unblock($user)), __('messages.user_unblocked'));
    }
}
