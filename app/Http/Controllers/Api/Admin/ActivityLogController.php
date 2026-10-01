<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /** ?action=user (prefix match), ?causer_id= */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'causer_id' => ['nullable', 'integer'],
        ]);

        $logs = ActivityLog::query()
            ->with('causer')
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', 'like', "{$action}%"))
            ->when($request->input('causer_id'), fn ($q, $id) => $q->where('causer_id', $id))
            ->latest('id')
            ->paginate($this->perPage(30));

        return $this->paginated($logs, __('messages.ok'), ActivityLogResource::class);
    }
}
