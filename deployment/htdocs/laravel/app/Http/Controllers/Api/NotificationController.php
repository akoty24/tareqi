<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['unread' => ['nullable', 'boolean']]);
        $user = $request->user();

        $query = $request->boolean('unread') ? $user->unreadNotifications() : $user->notifications();

        return $this->paginated(
            $query->paginate($this->perPage(20)),
            __('messages.ok'),
            NotificationResource::class,
            ['unread_count' => $user->unreadNotifications()->count()],
        );
    }

    /** Cheap endpoint for the badge (polled by the web and mobile apps). */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread_count' => $request->user()->unreadNotifications()->count()], __('messages.ok'));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        // Scoped to the user's own notifications: others' IDs are simply 404.
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return $this->success(new NotificationResource($item), __('messages.notification_read'));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, __('messages.notifications_read'));
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return $this->deleted(__('messages.notification_deleted'));
    }

    /** Clear the notification center: read notifications only, unread ones are kept. */
    public function destroyRead(Request $request): JsonResponse
    {
        $request->user()->readNotifications()->delete();

        return $this->deleted(__('messages.notifications_cleared'));
    }
}
