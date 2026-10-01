<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Trip;
use Database\Factories\Support\EgyptianData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a passenger rating the trip owner of a completed booking.
 *
 * @extends Factory<\App\Models\Rating>
 */
class RatingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => fn () => Booking::factory()->completed()
                ->for(Trip::factory()->status(TripStatus::Completed))->create()->id,
            'trip_id' => fn (array $a) => Booking::find($a['booking_id'])->trip_id,
            'rater_id' => fn (array $a) => Booking::find($a['booking_id'])->passenger_id,
            'rated_user_id' => fn (array $a) => Booking::find($a['booking_id'])->trip->owner_id,
            'stars' => fake()->randomElement([3, 4, 4, 5, 5, 5]),
            'review' => fake()->randomElement(EgyptianData::REVIEWS),
        ];
    }
}
