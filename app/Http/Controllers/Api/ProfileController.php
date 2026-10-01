<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdatePhotoRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = User::withCompletedTripCounts()->findOrFail($request->user()->id);

        return $this->success(new UserResource($user), __('messages.ok'));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        // A changed email must be verified again.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return $this->updated(new UserResource($user), __('messages.profile_updated'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->input('password');
        $user->save();

        // Log out other devices; keep the current token.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return $this->success(null, __('messages.password_updated'));
    }

    /** Email / push preferences; in-app notifications are always kept. */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['sometimes', 'boolean'],
            'push' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->fill(array_filter([
            'notify_email' => $data['email'] ?? null,
            'notify_push' => $data['push'] ?? null,
        ], fn ($value) => $value !== null))->save();

        return $this->updated(new UserResource($user), __('messages.notification_settings_updated'));
    }

    public function updatePhoto(UpdatePhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        $old = $user->profile_photo_path;

        $user->profile_photo_path = $request->file('photo')->store('profile-photos', 'public');
        $user->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return $this->updated(new UserResource($user), __('messages.profile_updated'));
    }
}
