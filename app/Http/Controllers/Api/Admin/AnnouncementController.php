<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Announcement::query()->with('sender')->latest('id')->paginate($this->perPage(20));

        return $this->paginated($announcements, __('messages.ok'), AnnouncementResource::class);
    }

    /** Broadcast an in-app (+ push, + optional email) notification. */
    public function store(AnnouncementRequest $request, AnnouncementService $announcements): JsonResponse
    {
        $announcement = $announcements->send($request->user(), $request->validated());

        return $this->created(
            new AnnouncementResource($announcement->load('sender')),
            trans_choice('messages.announcement_sent', $announcement->recipients_count, ['count' => $announcement->recipients_count]),
        );
    }
}
