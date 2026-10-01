<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note: factories do not adjust trips.available_seats. Use BookingService in
 * application code; seeders recalculate seats after creating bookings.
 *
 * @extends Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'passenger_id' => User::factory(),
            'seats' => 1,
            'status' => BookingStatus::Confirmed,
            'notes' => fake()->randomElement(['هستناك عند المدخل', 'معايا شنطة صغيرة', null, null]),
            'price_per_seat' => fn (array $a) => Trip::find($a['trip_id'])->currentSeatPrice(),
            'total_price' => fn (array $a) => number_format((float) $a['price_per_seat'] * $a['seats'], 2, '.', ''),
            'confirmed_at' => fn (array $a) => $a['status'] === BookingStatus::Confirmed ? now() : null,
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'confirmed_at' => in_array($status, [BookingStatus::Confirmed, BookingStatus::Completed], true) ? now() : null,
            'cancelled_at' => $status === BookingStatus::Cancelled ? now() : null,
        ]);
    }

    public function pending(): static
    {
        return $this->status(BookingStatus::Pending);
    }

    public function completed(): static
    {
        return $this->status(BookingStatus::Completed);
    }
}
