<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Services\ActivityLogger;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'stars' => ['nullable', 'integer', 'between:1,5'],
            'search' => ['nullable', 'string', 'max:100'],
            'with_review' => ['nullable', 'boolean'],
        ]);

        $ratings = Rating::query()
            ->with(['rater', 'ratedUser', 'trip'])
            ->when($request->input('stars'), fn ($q, $stars) => $q->where('stars', $stars))
            ->when($request->boolean('with_review'), fn ($q) => $q->whereNotNull('review')->where('review', '!=', ''))
            ->when($request->input('search'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->whereHas('rater', fn ($q) => $q->search($term))
                ->orWhereHas('ratedUser', fn ($q) => $q->search($term))))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($ratings, __('messages.ok'), RatingResource::class);
    }

    /** Delete an abusive rating; the rated user's average is recalculated. */
    public function destroy(Rating $rating, RatingService $ratings, ActivityLogger $activity): JsonResponse
    {
        $ratings->delete($rating);
        $activity->log('rating.deleted', $rating, ['stars' => $rating->stars, 'review' => $rating->review]);

        return $this->deleted(__('messages.rating_deleted'));
    }
}
