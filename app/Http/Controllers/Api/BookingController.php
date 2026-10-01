<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    /**
     * GET /bookings              my bookings as a passenger
     * GET /bookings?role=owner   bookings on trips I own
     *   &scope=upcoming|past     ordered by trip departure
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['nullable', 'in:passenger,owner'],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'scope' => ['nullable', 'in:upcoming,past'],
        ]);
        $user = $request->user();
        $scope = $request->input('scope');

        $query = $request->input('role') === 'owner'
            ? Booking::query()->whereHas('trip', fn ($q) => $q->where('owner_id', $user->id))
            : $user->bookings();

        // Order by the trip's departure (correlated subqueries, no join needed):
        // upcoming = soonest first, past = most recent first.
        $tripColumn = fn (string $column) => Trip::query()->select($column)->whereColumn('trips.id', 'bookings.trip_id');

        $bookings = $query
            ->with(['trip.owner', 'trip.vehicle', 'passenger', 'ratings'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($scope === 'upcoming', fn ($q) => $q
                ->whereHas('trip', fn ($t) => $t->whereDate('departure_date', '>=', today()))
                ->orderBy($tripColumn('departure_date'))
                ->orderBy($tripColumn('departure_time')))
            ->when($scope === 'past', fn ($q) => $q
                ->whereHas('trip', fn ($t) => $t->whereDate('departure_date', '<', today()))
                ->orderByDesc($tripColumn('departure_date'))
                ->orderByDesc($tripColumn('departure_time')))
            ->latest('id')
            ->paginate($this->perPage());

        return $this->paginated($bookings, __('messages.ok'), BookingResource::class);
    }

    public function store(StoreBookingRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('book', $trip);

        $booking = $this->bookings->create($trip, $request->user(), $request->integer('seats'), $request->input('notes'));

        $message = $booking->status === BookingStatus::Confirmed
            ? __('messages.booking_created_confirmed')
            : __('messages.booking_created_pending');

        return $this->created(new BookingResource($booking->load(['trip.owner', 'trip.vehicle'])), $message);
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return $this->success(new BookingResource($booking->load(['trip.owner', 'trip.vehicle', 'passenger', 'ratings'])), __('messages.ok'));
    }

    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('confirm', $booking);

        $booking = $this->bookings->confirm($booking, $request->user());

        return $this->updated(new BookingResource($booking->load('passenger')), __('messages.booking_confirmed'));
    }

    public function reject(ReasonRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('reject', $booking);

        $booking = $this->bookings->reject($booking, $request->user(), $request->input('reason'));

        return $this->updated(new BookingResource($booking->load('passenger')), __('messages.booking_rejected'));
    }

    public function cancel(ReasonRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        $booking = $this->bookings->cancel($booking, $request->user(), $request->input('reason'));

        return $this->updated(new BookingResource($booking->load('passenger')), __('messages.booking_cancelled'));
    }
}
