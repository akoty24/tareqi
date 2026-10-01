<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TripRequestRequest;
use App\Http\Resources\TripRequestResource;
use App\Http\Resources\TripResource;
use App\Models\TripRequest;
use App\Services\Matching\TripRequestMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripRequestController extends Controller
{
    public function __construct(private readonly TripRequestMatchingService $matching)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::enum(TripRequestStatus::class)]]);

        $requests = $request->user()->tripRequests()
            ->withCount('matchedTrips')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [TripRequestStatus::Active->value])
            ->orderByDesc('requested_date')
            ->paginate($this->perPage());

        return $this->paginated($requests, __('messages.ok'), TripRequestResource::class);
    }

    /** Creating a request immediately returns currently matching trips. */
    public function store(TripRequestRequest $request): JsonResponse
    {
        $this->authorize('create', TripRequest::class);

        $tripRequest = new TripRequest($request->validated());
        $tripRequest->user_id = $request->user()->id;
        $tripRequest->status = TripRequestStatus::Active;
        $tripRequest->save();
        $tripRequest->setRelation('user', $request->user());

        return $this->created([
            'trip_request' => new TripRequestResource($tripRequest),
            'matching_trips' => TripResource::collection($this->matching->suggestionsFor($tripRequest)),
        ], __('messages.trip_request_created'));
    }

    public function show(TripRequest $tripRequest): JsonResponse
    {
        $this->authorize('view', $tripRequest);

        $tripRequest->loadCount('matchedTrips')->load('user');
        $suggestions = $tripRequest->status === TripRequestStatus::Active
            ? $this->matching->suggestionsFor($tripRequest)
            : collect();

        return $this->success([
            'trip_request' => new TripRequestResource($tripRequest),
            'matching_trips' => TripResource::collection($suggestions),
        ], __('messages.ok'));
    }

    public function update(TripRequestRequest $request, TripRequest $tripRequest): JsonResponse
    {
        $this->authorize('update', $tripRequest);
        $this->ensureActive($tripRequest);

        $tripRequest->update($request->validated());

        return $this->updated(new TripRequestResource($tripRequest), __('messages.trip_request_updated'));
    }

    /** Requests are cancelled (kept for history/statistics), not hard-deleted. */
    public function destroy(TripRequest $tripRequest): JsonResponse
    {
        $this->authorize('delete', $tripRequest);
        $this->ensureActive($tripRequest);

        $tripRequest->status = TripRequestStatus::Cancelled;
        $tripRequest->save();

        return $this->success(new TripRequestResource($tripRequest), __('messages.trip_request_cancelled'));
    }

    private function ensureActive(TripRequest $tripRequest): void
    {
        if ($tripRequest->status !== TripRequestStatus::Active) {
            throw BusinessRuleException::make('trip_request_not_active');
        }
    }
}
