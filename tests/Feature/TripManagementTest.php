<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\TripCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TripManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tripPayload(User $driver, array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $driver->vehicles->first()->id,
            'origin' => 'ميت خاقان',
            'destination' => 'شبين الكوم',
            'departure_date' => now()->addDays(2)->toDateString(),
            'departure_time' => '07:30',
            'total_seats' => 3,
            'cost_type' => 'fixed_price',
            'price_per_seat' => 25,
        ], $overrides);
    }

    public function test_driver_can_create_published_trip(): void
    {
        $driver = $this->driver();
        $this->actingAsUser($driver);

        $response = $this->postJson('/api/trips', $this->tripPayload($driver));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.available_seats', 3)
            ->assertJsonPath('data.price_per_seat', 25)
            ->assertJsonPath('data.departure_time', '07:30')
            ->assertJsonPath('data.is_mine', true);

        $this->assertDatabaseHas('trips', [
            'owner_id' => $driver->id,
            'origin_normalized' => 'ميت خاقان',
            'status' => 'published',
        ]);
    }

    public function test_trip_can_be_saved_as_draft(): void
    {
        $driver = $this->driver();
        $this->actingAsUser($driver);

        $this->postJson('/api/trips', $this->tripPayload($driver, ['publish' => false]))
            ->assertCreated()->assertJsonPath('data.status', 'draft');
    }

    public function test_pricing_rules_per_cost_type(): void
    {
        $driver = $this->driver();
        $this->actingAsUser($driver);

        // FIXED_PRICE requires price_per_seat.
        $this->postJson('/api/trips', $this->tripPayload($driver, ['price_per_seat' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('price_per_seat');

        // COST_SHARING requires estimated_cost_per_passenger.
        $this->postJson('/api/trips', $this->tripPayload($driver, ['cost_type' => 'cost_sharing', 'price_per_seat' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('estimated_cost_per_passenger');

        // FREE clears any price that was sent.
        $this->postJson('/api/trips', $this->tripPayload($driver, [
            'cost_type' => 'free', 'price_per_seat' => 50, 'estimated_cost_per_passenger' => 20,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.price_per_seat', null)
            ->assertJsonPath('data.estimated_cost_per_passenger', null)
            ->assertJsonPath('data.seat_price', 0);
    }

    public function test_cannot_create_trip_with_someone_elses_vehicle(): void
    {
        $driver = $this->driver();
        $otherVehicle = Vehicle::factory()->create();
        $this->actingAsUser($driver);

        $this->postJson('/api/trips', $this->tripPayload($driver, ['vehicle_id' => $otherVehicle->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    }

    public function test_cannot_create_trip_in_the_past(): void
    {
        $driver = $this->driver();
        $this->actingAsUser($driver);

        $this->postJson('/api/trips', $this->tripPayload($driver, ['departure_date' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('departure_date');
    }

    public function test_blocked_user_cannot_create_trip(): void
    {
        $driver = $this->driver();
        $driver->forceFill(['status' => 'blocked'])->save();
        $this->actingAsUser($driver);

        $this->postJson('/api/trips', $this->tripPayload($driver))
            ->assertForbidden()->assertJsonPath('error_code', 'account_blocked');
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_trip_with_return_trip_creates_two_independent_trips(): void
    {
        $driver = $this->driver();
        $this->actingAsUser($driver);

        $response = $this->postJson('/api/trips', $this->tripPayload($driver, [
            'return_trip' => ['departure_date' => now()->addDays(2)->toDateString(), 'departure_time' => '17:00'],
        ]))->assertCreated();

        $outbound = Trip::findOrFail($response->json('data.id'));
        $return = $outbound->returnTrip;

        $this->assertNotNull($return);
        $this->assertSame($outbound->id, $return->parent_trip_id);
        $this->assertSame('شبين الكوم', $return->origin);
        $this->assertSame('ميت خاقان', $return->destination);
        $this->assertSame(TripStatus::Published, $return->status);
        $this->assertSame(3, $return->available_seats);
        $response->assertJsonPath('data.return_trip.id', $return->id);
    }

    public function test_return_trip_must_depart_after_outbound(): void
    {
        $driver = $this->driver();
        $this->actingAsUser($driver);

        $this->postJson('/api/trips', $this->tripPayload($driver, [
            'return_trip' => ['departure_date' => now()->addDays(2)->toDateString(), 'departure_time' => '06:00'],
        ]))->assertUnprocessable()->assertJsonPath('error_code', 'return_before_outbound');

        $this->assertDatabaseCount('trips', 0); // whole creation rolled back
    }

    public function test_return_trip_can_be_added_later_but_only_once(): void
    {
        $trip = $this->publishedTrip();
        $this->actingAsUser($trip->owner);

        $payload = ['departure_date' => $trip->departure_date->toDateString(), 'departure_time' => '18:00'];
        $this->postJson("/api/trips/{$trip->id}/return-trip", $payload)
            ->assertCreated()->assertJsonPath('data.is_return_trip', true);

        $this->postJson("/api/trips/{$trip->id}/return-trip", $payload)
            ->assertUnprocessable()->assertJsonPath('error_code', 'return_trip_exists');
    }

    public function test_owner_can_update_trip_and_price_change_does_not_affect_existing_bookings(): void
    {
        $trip = $this->publishedTrip(['cost_type' => 'fixed_price', 'price_per_seat' => 100, 'estimated_cost_per_passenger' => null]);
        $booking = Booking::factory()->for($trip)->create(['seats' => 2]);
        $trip->forceFill(['available_seats' => $trip->total_seats - 2])->save();

        $this->actingAsUser($trip->owner);
        $this->putJson("/api/trips/{$trip->id}", ['price_per_seat' => 150, 'notes' => 'ملاحظة جديدة'])
            ->assertOk()->assertJsonPath('data.price_per_seat', 150);

        $booking->refresh();
        $this->assertEquals(100, $booking->price_per_seat);
        $this->assertEquals(200, $booking->total_price);
    }

    public function test_other_user_cannot_modify_trip(): void
    {
        $trip = $this->publishedTrip();
        $this->actingAsUser();

        $this->putJson("/api/trips/{$trip->id}", ['notes' => 'hack'])->assertForbidden();
        $this->patchJson("/api/trips/{$trip->id}/cancel")->assertForbidden();
        $this->deleteJson("/api/trips/{$trip->id}")->assertForbidden();

        $this->assertSame(TripStatus::Published, $trip->fresh()->status);
    }

    public function test_seats_cannot_drop_below_booked_and_route_is_locked_after_booking(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 4, 'available_seats' => 4]);
        Booking::factory()->for($trip)->create(['seats' => 3]);
        $trip->forceFill(['available_seats' => 1])->save();
        $this->actingAsUser($trip->owner);

        $this->putJson("/api/trips/{$trip->id}", ['total_seats' => 2])
            ->assertUnprocessable()->assertJsonPath('error_code', 'seats_below_booked');

        $this->putJson("/api/trips/{$trip->id}", ['destination' => 'دمنهور'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'trip_has_bookings');

        // Reducing to exactly the booked seats makes the trip FULL.
        $this->putJson("/api/trips/{$trip->id}", ['total_seats' => 3])
            ->assertOk()->assertJsonPath('data.status', 'full')->assertJsonPath('data.available_seats', 0);
    }

    public function test_owner_cancelling_trip_cancels_bookings_and_notifies_passengers(): void
    {
        Notification::fake();
        $trip = $this->publishedTrip();
        $booking = Booking::factory()->for($trip)->create();
        $pending = Booking::factory()->for($trip)->pending()->create();
        $this->actingAsUser($trip->owner);

        $this->patchJson("/api/trips/{$trip->id}/cancel", ['reason' => 'ظرف طارئ'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $pending->fresh()->status);
        Notification::assertSentTo([$booking->passenger, $pending->passenger], TripCancelledNotification::class);
        Notification::assertNotSentTo($trip->owner, TripCancelledNotification::class);
    }

    public function test_trip_with_bookings_cannot_be_deleted_but_empty_trip_can(): void
    {
        $booked = $this->publishedTrip();
        Booking::factory()->for($booked)->create();
        $empty = $this->publishedTrip([], $booked->owner);
        $this->actingAsUser($booked->owner);

        $this->deleteJson("/api/trips/{$booked->id}")->assertStatus(409)->assertJsonPath('error_code', 'trip_has_bookings_delete');
        $this->deleteJson("/api/trips/{$empty->id}")->assertOk();

        $this->assertModelExists($booked);
        $this->assertModelMissing($empty);
    }

    public function test_draft_trip_is_hidden_from_other_users_until_published(): void
    {
        $trip = $this->publishedTrip(['status' => TripStatus::Draft]);

        $this->actingAsUser();
        $this->getJson("/api/trips/{$trip->id}")->assertForbidden();

        $this->actingAsUser($trip->owner);
        $this->patchJson("/api/trips/{$trip->id}/publish")->assertOk()->assertJsonPath('data.status', 'published');

        $this->actingAsUser();
        $this->getJson("/api/trips/{$trip->id}")->assertOk();
    }

    public function test_trip_lifecycle_start_and_complete(): void
    {
        $trip = $this->publishedTrip();
        $confirmed = Booking::factory()->for($trip)->create();
        $pending = Booking::factory()->for($trip)->pending()->create();
        $trip->forceFill(['available_seats' => $trip->total_seats - 2])->save();
        $this->actingAsUser($trip->owner);

        $this->patchJson("/api/trips/{$trip->id}/start")->assertUnprocessable()->assertJsonPath('error_code', 'trip_too_early_to_start');

        $this->travelTo($trip->departureAt()->subMinutes(30));
        $this->patchJson("/api/trips/{$trip->id}/start")->assertOk()->assertJsonPath('data.status', 'started');
        $this->assertSame(BookingStatus::Rejected, $pending->fresh()->status);

        $this->patchJson("/api/trips/{$trip->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(BookingStatus::Completed, $confirmed->fresh()->status);
    }

    public function test_owner_sees_own_trips_with_mine_filter(): void
    {
        $trip = $this->publishedTrip();
        $this->publishedTrip(); // someone else's
        $this->actingAsUser($trip->owner);

        $this->getJson('/api/trips?mine=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trip->id)
            ->assertJsonPath('meta.total', 1);
    }
}
