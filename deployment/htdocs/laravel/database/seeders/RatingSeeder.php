<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Rating;
use App\Services\RatingService;
use Illuminate\Database\Seeder;

class RatingSeeder extends Seeder
{
    public function run(RatingService $ratings): void
    {
        $bookings = Booking::query()->where('status', BookingStatus::Completed)->with(['trip', 'passenger'])->get();
        $leftUnrated = false;

        foreach ($bookings as $booking) {
            // Leave one of the demo passenger's bookings unrated so the "rate" flow can be tried.
            if (! $leftUnrated && $booking->passenger->email === 'passenger@mishwar.test') {
                $leftUnrated = true;
                continue;
            }

            // Passenger rates the trip owner.
            Rating::factory()->create([
                'booking_id' => $booking->id,
                'trip_id' => $booking->trip_id,
                'rater_id' => $booking->passenger_id,
                'rated_user_id' => $booking->trip->owner_id,
            ]);

            // Owner rates the passenger (optional, ~half the time).
            if (fake()->boolean()) {
                Rating::factory()->create([
                    'booking_id' => $booking->id,
                    'trip_id' => $booking->trip_id,
                    'rater_id' => $booking->trip->owner_id,
                    'rated_user_id' => $booking->passenger_id,
                ]);
            }
        }

        // Sync the denormalized rating_average / ratings_count.
        Rating::query()->distinct()->pluck('rated_user_id')
            ->each(fn (int $userId) => $ratings->recalculate($userId));
    }
}
