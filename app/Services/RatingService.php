<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RatingService
{
    /**
     * Eligibility (participant, completed, not self, not duplicate) is checked
     * by RatingPolicy; the unique index is the final guard against races.
     */
    public function rate(Booking $booking, User $rater, int $stars, ?string $review = null): Rating
    {
        $booking->loadMissing('trip');
        $ratedUserId = $booking->isPassenger($rater) ? $booking->trip->owner_id : $booking->passenger_id;

        try {
            return DB::transaction(function () use ($booking, $rater, $ratedUserId, $stars, $review) {
                $rating = new Rating(['stars' => $stars, 'review' => $review]);
                $rating->booking_id = $booking->id;
                $rating->trip_id = $booking->trip_id;
                $rating->rater_id = $rater->id;
                $rating->rated_user_id = $ratedUserId;
                $rating->save();

                $this->recalculate($ratedUserId);

                return $rating;
            });
        } catch (UniqueConstraintViolationException) {
            throw BusinessRuleException::make('already_rated', 409);
        }
    }

    /** Re-sync the denormalized users.rating_average / ratings_count. */
    public function recalculate(int $userId): void
    {
        $user = User::query()->lockForUpdate()->findOrFail($userId);

        $stats = Rating::query()
            ->where('rated_user_id', $userId)
            ->selectRaw('COUNT(*) as total, AVG(stars) as average')
            ->first();

        $user->forceFill([
            'ratings_count' => (int) $stats->total,
            'rating_average' => round((float) $stats->average, 2),
        ])->save();
    }
}
