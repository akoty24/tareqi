<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(TripStatus::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $trips = Trip::query()
            ->with(['owner', 'vehicle'])
            ->withCount('bookings')
            ->when($request->input('search'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->fromPlace($term)
                ->orWhere(fn ($q) => $q->toPlace($term))
                ->orWhereHas('owner', fn ($q) => $q->search($term))))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->onDate($request->input('date'))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($trips, __('messages.ok'), TripResource::class);
    }

    /** Cancel an inappropriate trip; passengers and the owner are notified. */
    public function cancel(ReasonRequest $request, Trip $trip, TripService $trips): JsonResponse
    {
        $this->authorize('moderate', $trip);

        $trip = $trips->cancel($trip, $request->input('reason'), byAdmin: true);

        return $this->updated(new TripResource($trip), __('messages.trip_cancelled'));
    }
}
