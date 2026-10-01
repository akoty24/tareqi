<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Jobs\CompleteDepartedTrips;
use App\Models\Booking;
use App\Models\TripRequest;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\TripCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LifecycleAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_departed_trips_are_completed_automatically_so_they_can_be_rated(): void
    {
        Notification::fake();
        $trip = $this->publishedTrip();
        $confirmed = Booking::factory()->for($trip)->create();
        $pending = Booking::factory()->for($trip)->pending()->create();
        $future = $this->publishedTrip(['departure_date' => now()->addDays(3)->toDateString()]);

        // 5 hours after departure: still inside the grace period.
        $this->travelTo($trip->departureAt()->addHours(5));
        (new CompleteDepartedTrips)->handle(app(\App\Services\TripService::class));
        $this->assertSame(TripStatus::Published, $trip->fresh()->status);

        $this->travelTo($trip->departureAt()->addHours(7));
        (new CompleteDepartedTrips)->handle(app(\App\Services\TripService::class));

        $this->assertSame(TripStatus::Completed, $trip->fresh()->status);
        $this->assertSame(BookingStatus::Completed, $confirmed->fresh()->status);
        $this->assertSame(BookingStatus::Rejected, $pending->fresh()->status);
        $this->assertSame(TripStatus::Published, $future->fresh()->status);
        Notification::assertSentTo($pending->passenger, BookingRejectedNotification::class);

        // The passenger can now rate the driver.
        $this->actingAsUser($confirmed->passenger);
        $this->postJson("/api/bookings/{$confirmed->id}/rating", ['stars' => 5])->assertCreated();
    }

    public function test_blocking_a_driver_cancels_their_upcoming_trips_and_notifies_passengers(): void
    {
        Notification::fake();
        $trip = $this->publishedTrip();
        $booking = Booking::factory()->for($trip)->create();
        $done = $this->publishedTrip(['status' => TripStatus::Completed, 'departure_date' => now()->subDay()->toDateString()], $trip->owner);
        $this->actingAsAdmin();

        $this->patchJson("/api/admin/users/{$trip->owner_id}/block")->assertOk();

        $this->assertSame(TripStatus::Cancelled, $trip->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(TripStatus::Completed, $done->fresh()->status); // history untouched
        Notification::assertSentTo($booking->passenger, TripCancelledNotification::class);
    }

    public function test_blocking_a_passenger_cancels_their_bookings_and_frees_the_seats(): void
    {
        Notification::fake();
        $trip = $this->publishedTrip(['total_seats' => 3, 'available_seats' => 1]);
        $passenger = User::factory()->create();
        $booking = Booking::factory()->for($trip)->for($passenger, 'passenger')->create(['seats' => 2]);
        $this->actingAsAdmin();

        $this->patchJson("/api/admin/users/{$passenger->id}/block")->assertOk();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(3, $trip->fresh()->available_seats);
        Notification::assertSentTo($trip->owner, BookingCancelledNotification::class);
    }

    public function test_trip_request_creation_is_rate_limited(): void
    {
        $this->actingAsUser();
        $payload = fn () => [
            'origin' => 'كمشيش', 'destination' => 'طنطا',
            'requested_date' => now()->addDay()->toDateString(), 'passengers_count' => 1,
        ];

        foreach (range(1, 20) as $_) {
            $this->postJson('/api/trip-requests', $payload())->assertCreated();
        }
        $this->postJson('/api/trip-requests', $payload())->assertStatus(429);
        $this->assertSame(20, TripRequest::count());
    }
}
