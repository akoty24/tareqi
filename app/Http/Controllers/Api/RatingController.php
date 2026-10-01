<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Booking;
use App\Models\Rating;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    /** GET /ratings?type=received|given */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'in:received,given']]);
        $user = $request->user();

        $query = $request->input('type') === 'given' ? $user->ratingsGiven() : $user->ratingsReceived();

        $ratings = $query->with(['rater', 'ratedUser', 'trip'])->latest()->paginate($this->perPage());

        return $this->paginated($ratings, __('messages.ok'), RatingResource::class);
    }

    /** The rated user is the other party of the booking (owner <-> passenger). */
    public function store(StoreRatingRequest $request, Booking $booking, RatingService $ratings): JsonResponse
    {
        $this->authorize('create', [Rating::class, $booking]);

        $rating = $ratings->rate($booking, $request->user(), $request->integer('stars'), $request->input('review'));

        return $this->created(new RatingResource($rating->load(['ratedUser', 'trip'])), __('messages.rating_created'));
    }
}
