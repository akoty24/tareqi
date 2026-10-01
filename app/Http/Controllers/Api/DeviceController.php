<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Push notification registration of the mobile app (FCM tokens). */
class DeviceController extends Controller
{
    /** Register (or move) a device token to the current user. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'in:android,ios,web'],
        ]);

        $device = DeviceToken::query()->firstOrNew(['token' => $data['token']]);
        $device->user()->associate($request->user());
        $device->platform = $data['platform'] ?? 'android';
        $device->last_used_at = now();
        $device->save();

        return $this->success(null, __('messages.device_registered'));
    }

    /** Called on logout so the device stops receiving this user's pushes. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        $request->user()->deviceTokens()->where('token', $data['token'])->delete();

        return $this->deleted(__('messages.device_removed'));
    }
}
