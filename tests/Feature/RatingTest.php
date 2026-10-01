<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Rating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    private function completedBooking(): Booking
    {
        $trip = $this->publishedTrip(['status' => TripStatus::Completed, 'departure_date' => now()->subDay()->toDateString()]);

        return Booking::factory()->for($trip)->completed()->create();
    }

    public function test_passenger_rates_owner_after_completion_and_average_updates(): void
    {
        $booking = $this->completedBooking();
        $owner = $booking->trip->owner;
        $this->actingAsUser($booking->passenger);

        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 4, 'review' => 'رحلة ممتازة'])
            ->assertCreated()
            ->assertJsonPath('data.stars', 4)
            ->assertJsonPath('data.rated_user.id', $owner->id);

        $second = Booking::factory()->for($booking->trip)->completed()->create();
        $this->actingAsUser($second->passenger);
        $this->postJson("/api/bookings/{$second->id}/rating", ['stars' => 5])->assertCreated();

        $owner->refresh();
        $this->assertSame(2, $owner->ratings_count);
        $this->assertEquals(4.5, (float) $owner->rating_average);
    }

    public function test_owner_can_rate_passenger(): void
    {
        $booking = $this->completedBooking();
        $this->actingAsUser($booking->trip->owner);

        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 5])
            ->assertCreated()
            ->assertJsonPath('data.rated_user.id', $booking->passenger_id);
    }

    public function test_cannot_rate_before_trip_is_completed(): void
    {
        $booking = Booking::factory()->for($this->publishedTrip())->create();
        $this->actingAsUser($booking->passenger);

        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 5])->assertForbidden();
        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_cannot_rate_twice_for_the_same_booking(): void
    {
        $booking = $this->completedBooking();
        $this->actingAsUser($booking->passenger);

        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 5])->assertCreated();
        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 1])->assertForbidden();

        $this->assertSame(1, Rating::count());
    }

    public function test_non_participant_cannot_rate(): void
    {
        $booking = $this->completedBooking();
        $this->actingAsUser();

        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 1])->assertForbidden();
    }

    public function test_stars_must_be_between_1_and_5(): void
    {
        $booking = $this->completedBooking();
        $this->actingAsUser($booking->passenger);

        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 6])->assertJsonValidationErrors('stars');
        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 0])->assertJsonValidationErrors('stars');
    }

    public function test_ratings_listing_received_and_given(): void
    {
        $booking = $this->completedBooking();
        $this->actingAsUser($booking->passenger);
        $this->postJson("/api/bookings/{$booking->id}/rating", ['stars' => 5])->assertCreated();

        $this->getJson('/api/ratings?type=given')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/ratings?type=received')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsUser($booking->trip->owner);
        $this->getJson('/api/ratings')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/users/{$booking->trip->owner_id}")->assertOk()
            ->assertJsonPath('data.user.ratings_count', 1)
            ->assertJsonCount(1, 'data.recent_ratings');
    }
}
