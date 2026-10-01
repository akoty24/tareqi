<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\Trip\SearchTripsRequest;
use App\Http\Requests\Trip\StoreReturnTripRequest;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\TripMatchingService;
use App\Services\TripService;
use App\Support\PlaceName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripController extends Controller
{
    public function __construct(private readonly TripService $trips)
    {
    }

    /**
     * GET /trips            upcoming bookable trips (browse)
     * GET /trips?mine=1     the authenticated user's own trips (any status)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'mine' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(TripStatus::class)],
            'scope' => ['nullable', 'in:upcoming,past'],
        ]);
        $user = $request->user();

        if ($request->boolean('mine')) {
            $query = $user->trips()
                ->with(['vehicle', 'returnTrip', 'parentTrip'])
                ->withCount(['bookings as pending_bookings_count' => fn ($q) => $q->where('status', BookingStatus::Pending)])
                ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->input('scope') === 'upcoming', fn ($q) => $q->whereDate('departure_date', '>=', today()))
                ->when($request->input('scope') === 'past', fn ($q) => $q->whereDate('departure_date', '<', today()))
                ->orderByDesc('departure_date')
                ->orderByDesc('departure_time');
        } else {
            $query = Trip::query()
                ->bookable()
                ->where('owner_id', '!=', $user->id)
                ->with(['owner', 'vehicle'])
                ->orderBy('departure_date')
                ->orderBy('departure_time');
        }

        return $this->paginated($query->paginate($this->perPage()), __('messages.ok'), TripResource::class);
    }

    /** Server-side search ranked by TripMatchingService score. */
    public function search(SearchTripsRequest $request, TripMatchingService $matcher): JsonResponse
    {
        $criteria = MatchCriteria::fromArray($request->validated());
        $results = $matcher->search($criteria, $request->user(), $this->perPage(), max(1, $request->integer('page', 1)));

        return $this->paginated($results, __('messages.ok'), TripResource::class);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $this->authorize('create', Trip::class);

        $trip = $this->trips->create($request->user(), $request->validated());

        return $this->created(new TripResource($trip), __('messages.trip_created'));
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        $user = $request->user();

        $trip->load(['owner' => fn ($q) => $q->withCompletedTripCounts(), 'vehicle', 'returnTrip', 'parentTrip']);
        $trip->setRelation('viewerBooking', $trip->bookings()
            ->where('passenger_id', $user->id)
            ->with('ratings')
            ->latest('id')
            ->first());

        if ($trip->isOwnedBy($user)) {
            $trip->load(['bookings' => fn ($q) => $q->with(['passenger', 'ratings'])->latest('id')]);
        }

        return $this->success(new TripResource($trip), __('messages.ok'));
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $trip = $this->trips->update($trip, $request->validated());

        return $this->updated(new TripResource($trip->load(['vehicle', 'returnTrip'])), __('messages.trip_updated'));
    }

    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        $this->trips->delete($trip);

        return $this->deleted(__('messages.trip_deleted'));
    }

    public function publish(Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        return $this->updated(new TripResource($this->trips->publish($trip)), __('messages.trip_published'));
    }

    public function cancel(ReasonRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $trip = $this->trips->cancel($trip, $request->input('reason'));

        return $this->updated(new TripResource($trip), __('messages.trip_cancelled'));
    }

    public function start(Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        return $this->updated(new TripResource($this->trips->start($trip)), __('messages.trip_started'));
    }

    public function complete(Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        return $this->updated(new TripResource($this->trips->complete($trip)), __('messages.trip_completed'));
    }

    public function storeReturn(StoreReturnTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $return = $this->trips->addReturnTrip($trip, $request->validated());

        return $this->created(new TripResource($return->load('parentTrip')), __('messages.return_trip_created'));
    }

    /** Autocomplete: known place names from existing trips. */
    public function places(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:60']]);
        $term = '%'.PlaceName::normalize($request->input('q')).'%';

        $origins = Trip::query()->select('origin as name')->where('origin_normalized', 'like', $term);
        $destinations = Trip::query()->select('destination as name')->where('destination_normalized', 'like', $term);

        $names = $origins->union($destinations)->limit(10)->pluck('name')->unique()->values();

        return $this->success($names, __('messages.ok'));
    }
}
