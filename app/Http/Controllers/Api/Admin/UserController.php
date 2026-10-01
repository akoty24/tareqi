<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\VehicleResource;
use App\Models\Report;
use App\Models\Role;
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

    /** ?role=member|staff|{role id} */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', 'string', 'max:20'],
        ]);

        $users = User::query()
            ->with('role')
            ->withCompletedTripCounts()
            ->search($request->input('search'))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('role'), fn ($q, $role) => match ($role) {
                'member' => $q->members(),
                'staff' => $q->staff(),
                default => $q->where('role_id', (int) $role),
            })
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($users, __('messages.ok'), UserResource::class);
    }

    /** Full account view with activity counters (support / moderation). */
    public function show(User $user): JsonResponse
    {
        $user = User::query()
            ->with(['role', 'vehicles' => fn ($q) => $q->withTrashed()->withCount('trips')])
            ->withCompletedTripCounts()
            ->withCount(['trips', 'bookings', 'tripRequests', 'ratingsGiven', 'ratingsReceived', 'deviceTokens', 'tokens'])
            ->findOrFail($user->id);

        return $this->success([
            'user' => new UserResource($user),
            'vehicles' => VehicleResource::collection($user->vehicles),
            'stats' => [
                'trips' => $user->trips_count,
                'bookings' => $user->bookings_count,
                'trip_requests' => $user->trip_requests_count,
                'ratings_given' => $user->ratings_given_count,
                'ratings_received' => $user->ratings_received_count,
                'reports_against' => Report::query()->where('reported_user_id', $user->id)->count(),
                'active_sessions' => $user->tokens_count,
                'devices' => $user->device_tokens_count,
            ],
        ], __('messages.ok'));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->moderation->update($user, $request->validated());

        return $this->updated(new UserResource($user->load('role')), __('messages.user_updated'));
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

    /** PATCH {role_id: int|null} — null turns the staff member back into a regular user. */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('assignRole', $user);
        $request->validate(['role_id' => ['present', 'nullable', 'integer', 'exists:roles,id']]);

        $role = $request->input('role_id') ? Role::findOrFail($request->integer('role_id')) : null;
        $user = $this->moderation->assignRole($request->user(), $user, $role);

        return $this->updated(new UserResource($user), __('messages.user_role_updated'));
    }

    public function revokeSessions(User $user): JsonResponse
    {
        $this->authorize('block', $user);

        $this->moderation->revokeSessions($user);

        return $this->success(null, __('messages.user_sessions_revoked'));
    }
}
