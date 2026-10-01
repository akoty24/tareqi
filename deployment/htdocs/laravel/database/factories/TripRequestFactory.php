<?php

namespace Database\Factories;

use App\Enums\TripRequestStatus;
use App\Models\User;
use Database\Factories\Support\EgyptianData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TripRequest>
 */
class TripRequestFactory extends Factory
{
    public function definition(): array
    {
        $from = fake()->numberBetween(6, 18);

        return [
            'user_id' => User::factory(),
            'origin' => fake()->randomElement(EgyptianData::VILLAGES),
            'destination' => fake()->randomElement(EgyptianData::CITIES),
            'requested_date' => now()->addDays(fake()->numberBetween(1, 10))->toDateString(),
            'preferred_time_from' => sprintf('%02d:00', $from),
            'preferred_time_to' => sprintf('%02d:00', $from + 2),
            'passengers_count' => fake()->numberBetween(1, 2),
            'notes' => fake()->randomElement(['محتاج أوصل الشغل قبل ٩', 'معايا شنطة كبيرة', 'رايح الجامعة', null]),
            'status' => TripRequestStatus::Active,
        ];
    }

    public function status(TripRequestStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => TripRequestStatus::Expired,
            'requested_date' => now()->subDays(fake()->numberBetween(1, 10))->toDateString(),
        ]);
    }
}
