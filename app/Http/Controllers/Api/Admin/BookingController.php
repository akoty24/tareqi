<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\ActivityLogger;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'trip_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $bookings = Booking::query()
            ->with(['trip.owner', 'passenger'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('trip_id'), fn ($q, $tripId) => $q->where('trip_id', $tripId))
            ->when($request->input('search'), fn ($q, $term) => $q->whereHas('passenger', fn ($q) => $q->search($term)))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($bookings, __('messages.ok'), BookingResource::class);
    }

    /** Cancel a booking (seats are released; passenger and owner are notified). */
    public function cancel(ReasonRequest $request, Booking $booking, BookingService $bookings, ActivityLogger $activity): JsonResponse
    {
        $this->authorize('moderate', $booking);

        $booking = $bookings->cancel($booking, $request->user(), $request->input('reason'));
        $activity->log('booking.cancelled', $booking, ['reason' => $request->input('reason')]);

        return $this->updated(new BookingResource($booking->load(['trip.owner', 'passenger'])), __('messages.booking_cancelled'));
    }
}
