<?php

namespace Database\Factories;

use App\Enums\CostType;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Database\Factories\Support\EgyptianData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $seats = fake()->numberBetween(2, 4);

        return [
            'owner_id' => User::factory(),
            'vehicle_id' => fn (array $attributes) => Vehicle::factory()->create(['user_id' => $attributes['owner_id']])->id,
            'origin' => fake()->randomElement(EgyptianData::VILLAGES),
            'destination' => fake()->randomElement(EgyptianData::CITIES),
            'departure_date' => now()->addDays(fake()->numberBetween(1, 14))->toDateString(),
            'departure_time' => sprintf('%02d:%02d', fake()->numberBetween(5, 21), fake()->randomElement([0, 15, 30, 45])),
            'total_seats' => $seats,
            'available_seats' => $seats,
            'cost_type' => CostType::CostSharing,
            'price_per_seat' => null,
            'estimated_cost_per_passenger' => fake()->randomElement([30, 40, 50, 60, 75]),
            'notes' => fake()->randomElement(EgyptianData::TRIP_NOTES),
            'auto_confirm_bookings' => fake()->boolean(40),
            'status' => TripStatus::Published,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'cost_type' => CostType::Free,
            'price_per_seat' => null,
            'estimated_cost_per_passenger' => null,
        ]);
    }

    public function fixedPrice(float $price = 60): static
    {
        return $this->state(fn () => [
            'cost_type' => CostType::FixedPrice,
            'price_per_seat' => $price,
            'estimated_cost_per_passenger' => null,
        ]);
    }

    public function costSharing(float $estimate = 50): static
    {
        return $this->state(fn () => [
            'cost_type' => CostType::CostSharing,
            'price_per_seat' => null,
            'estimated_cost_per_passenger' => $estimate,
        ]);
    }

    public function autoConfirm(bool $enabled = true): static
    {
        return $this->state(fn () => ['auto_confirm_bookings' => $enabled]);
    }

    public function seats(int $seats): static
    {
        return $this->state(fn () => ['total_seats' => $seats, 'available_seats' => $seats]);
    }

    public function status(TripStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function draft(): static
    {
        return $this->status(TripStatus::Draft);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => TripStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::Completed,
            'departure_date' => now()->subDays(fake()->numberBetween(2, 30))->toDateString(),
        ]);
    }

    public function route(string $origin, string $destination): static
    {
        return $this->state(fn () => ['origin' => $origin, 'destination' => $destination]);
    }

    public function departingAt(string $date, string $time): static
    {
        return $this->state(fn () => ['departure_date' => $date, 'departure_time' => $time]);
    }

    /** A return trip for $outbound: reversed route, same owner and vehicle, later departure. */
    public function returnOf(Trip $outbound, string $time = '17:00'): static
    {
        return $this->state(fn () => [
            'owner_id' => $outbound->owner_id,
            'vehicle_id' => $outbound->vehicle_id,
            'parent_trip_id' => $outbound->id,
            'origin' => $outbound->destination,
            'destination' => $outbound->origin,
            'departure_date' => $outbound->departure_date->toDateString(),
            'departure_time' => $time,
            'status' => $outbound->status,
        ]);
    }
}
