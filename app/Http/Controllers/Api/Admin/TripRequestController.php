<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TripRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\TripRequestResource;
use App\Models\TripRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(TripRequestStatus::class)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $requests = TripRequest::query()
            ->with('user')
            ->withCount('matchedTrips')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('search'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->fromPlace($term)
                ->orWhere(fn ($q) => $q->toPlace($term))))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($requests, __('messages.ok'), TripRequestResource::class);
    }

    /** Close a spam / inappropriate request (it stops matching new trips). */
    public function cancel(TripRequest $tripRequest, ActivityLogger $activity): JsonResponse
    {
        $this->authorize('moderate', $tripRequest);
        if ($tripRequest->status !== TripRequestStatus::Active) {
            throw BusinessRuleException::make('trip_request_not_active');
        }

        $tripRequest->status = TripRequestStatus::Cancelled;
        $tripRequest->save();
        $activity->log('trip_request.cancelled', $tripRequest, [
            'route' => "{$tripRequest->origin} ← {$tripRequest->destination}",
        ]);

        return $this->updated(new TripRequestResource($tripRequest->load('user')), __('messages.trip_request_cancelled'));
    }
}
