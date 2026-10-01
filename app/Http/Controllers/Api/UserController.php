<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Http\Resources\RatingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/** Public community profile: rating, completed trips and recent reviews. */
class UserController extends Controller
{
    public function show(int $user): JsonResponse
    {
        $user = User::withCompletedTripCounts()->findOrFail($user);

        $ratings = $user->ratingsReceived()
            ->with(['rater', 'trip:id,origin,destination,departure_date'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->success([
            'user' => new PublicUserResource($user),
            'recent_ratings' => RatingResource::collection($ratings),
        ], __('messages.ok'));
    }
}
