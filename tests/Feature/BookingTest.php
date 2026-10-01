<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\NewBookingRequestNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_auto_confirm_trip_creates_confirmed_booking_and_notifies_both_parties(): void
    {
        $trip = $this->publishedTrip(['auto_confirm_bookings' => true, 'total_seats' => 3, 'available_seats' => 3]);
        $passenger = $this->actingAsUser();

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 2])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(1, $trip->fresh()->available_seats);
        Notification::assertSentTo($passenger, BookingCreatedNotification::class);
        Notification::assertSentTo($trip->owner, NewBookingRequestNotification::class);
    }

    public function test_manual_trip_creates_pending_booking_that_still_holds_seats(): void
    {
        $trip = $this->publishedTrip(['auto_confirm_bookings' => false, 'total_seats' => 3, 'available_seats' => 3]);
        $this->actingAsUser();

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(2, $trip->fresh()->available_seats);
    }

    public function test_booking_stores_a_price_snapshot(): void
    {
        $trip = $this->publishedTrip(['cost_type' => 'fixed_price', 'price_per_seat' => 100, 'estimated_cost_per_passenger' => null]);
        $this->actingAsUser();

        $id = $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 2])
            ->assertCreated()
            ->assertJsonPath('data.price_per_seat', 100)
            ->assertJsonPath('data.total_price', 200)
            ->json('data.id');

        $trip->forceFill(['price_per_seat' => 180])->save();

        $booking = Booking::find($id);
        $this->assertEquals(100, $booking->price_per_seat);
        $this->assertEquals(200, $booking->total_price);
    }

    public function test_cost_sharing_and_free_price_snapshots(): void
    {
        $sharing = $this->publishedTrip(['cost_type' => 'cost_sharing', 'estimated_cost_per_passenger' => 40, 'price_per_seat' => null]);
        $free = $this->publishedTrip(['cost_type' => 'free', 'estimated_cost_per_passenger' => null, 'price_per_seat' => null]);
        $this->actingAsUser();

        $this->postJson("/api/trips/{$sharing->id}/bookings", ['seats' => 2])->assertJsonPath('data.total_price', 80);
        $this->postJson("/api/trips/{$free->id}/bookings", ['seats' => 2])->assertJsonPath('data.total_price', 0);
    }

    public function test_cannot_book_more_seats_than_available(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 2, 'available_seats' => 2]);
        $this->actingAsUser();

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 3])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'insufficient_seats');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_trip_becomes_full_and_rejects_further_bookings(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 2, 'available_seats' => 2, 'auto_confirm_bookings' => true]);

        $this->actingAsUser();
        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 2])->assertCreated();
        $this->assertSame(TripStatus::Full, $trip->fresh()->status);

        $this->actingAsUser();
        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'trip_full');
    }

    public function test_cancelling_a_booking_on_a_full_trip_reopens_it(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 2, 'available_seats' => 2, 'auto_confirm_bookings' => true]);
        $passenger = $this->actingAsUser();
        $bookingId = $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 2])->json('data.id');
        $this->assertSame(TripStatus::Full, $trip->fresh()->status);

        $this->patchJson("/api/bookings/{$bookingId}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

        $trip->refresh();
        $this->assertSame(TripStatus::Published, $trip->status);
        $this->assertSame(2, $trip->available_seats);
        Notification::assertSentTo($trip->owner, BookingCancelledNotification::class,
            fn ($n) => $n->cancelledByPassenger === true);
    }

    /**
     * Two requests load the same trip (3 seats) and both try to book 2 seats.
     * BookingService re-reads the trip under a row lock, so the second
     * request sees the decremented seat count and fails.
     */
    public function test_overbooking_is_prevented_even_with_stale_trip_instances(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 3, 'available_seats' => 3, 'auto_confirm_bookings' => true]);
        $staleCopyA = Trip::find($trip->id);
        $staleCopyB = Trip::find($trip->id);
        $service = app(BookingService::class);

        $service->create($staleCopyA, User::factory()->create(), 2);

        try {
            $service->create($staleCopyB, User::factory()->create(), 2);
            $this->fail('Second booking should have been rejected.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('insufficient_seats', $e->errorCode);
        }

        $this->assertSame(1, $trip->fresh()->available_seats);
        $this->assertSame(2, (int) Booking::where('trip_id', $trip->id)->sum('seats'));
    }

    public function test_many_sequential_bookings_never_exceed_capacity(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 4, 'available_seats' => 4, 'auto_confirm_bookings' => true]);
        $service = app(BookingService::class);
        $succeeded = 0;

        foreach (range(1, 6) as $_) {
            try {
                $service->create($trip, User::factory()->create(), 1);
                $succeeded++;
            } catch (BusinessRuleException) {
            }
        }

        $this->assertSame(4, $succeeded);
        $this->assertSame(0, $trip->fresh()->available_seats);
        $this->assertSame(TripStatus::Full, $trip->fresh()->status);
    }

    public function test_cannot_book_own_trip(): void
    {
        $trip = $this->publishedTrip();
        $this->actingAsUser($trip->owner);

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])->assertForbidden();
    }

    public function test_cannot_book_same_trip_twice_while_active(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 4, 'available_seats' => 4]);
        $this->actingAsUser();

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])->assertCreated();
        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])
            ->assertStatus(409)->assertJsonPath('error_code', 'already_booked');
    }

    /** @dataProvider unbookableStatuses */
    public function test_non_published_trips_cannot_be_booked(TripStatus $status): void
    {
        $trip = $this->publishedTrip(['status' => $status]);
        $this->actingAsUser();

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'trip_not_bookable');
    }

    public static function unbookableStatuses(): array
    {
        return [
            'draft' => [TripStatus::Draft],
            'started' => [TripStatus::Started],
            'completed' => [TripStatus::Completed],
            'cancelled' => [TripStatus::Cancelled],
        ];
    }

    public function test_past_trip_cannot_be_booked(): void
    {
        $trip = $this->publishedTrip(['departure_date' => now()->subDay()->toDateString()]);
        $this->actingAsUser();

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])
            ->assertUnprocessable()->assertJsonPath('error_code', 'trip_in_past');
    }

    public function test_blocked_user_cannot_book(): void
    {
        $trip = $this->publishedTrip();
        $this->actingAsUser(User::factory()->blocked()->create());

        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_owner_can_confirm_and_reject_pending_bookings(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 3, 'available_seats' => 1]);
        $a = Booking::factory()->for($trip)->pending()->create();
        $b = Booking::factory()->for($trip)->pending()->create();
        $this->actingAsUser($trip->owner);

        $this->patchJson("/api/bookings/{$a->id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->patchJson("/api/bookings/{$b->id}/reject", ['reason' => 'مفيش مكان للشنط'])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame(2, $trip->fresh()->available_seats); // b's seat released
        Notification::assertSentTo($a->passenger, BookingConfirmedNotification::class);
        Notification::assertSentTo($b->passenger, BookingRejectedNotification::class);

        // Only pending bookings can be confirmed.
        $this->patchJson("/api/bookings/{$a->id}/confirm")->assertUnprocessable()->assertJsonPath('error_code', 'booking_not_pending');
    }

    public function test_passenger_and_strangers_cannot_confirm_or_reject(): void
    {
        $trip = $this->publishedTrip();
        $booking = Booking::factory()->for($trip)->pending()->create();

        $this->actingAsUser($booking->passenger);
        $this->patchJson("/api/bookings/{$booking->id}/confirm")->assertForbidden();

        $this->actingAsUser();
        $this->patchJson("/api/bookings/{$booking->id}/reject")->assertForbidden();
        $this->patchJson("/api/bookings/{$booking->id}/cancel")->assertForbidden();
        $this->getJson("/api/bookings/{$booking->id}")->assertForbidden();

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_owner_cancelling_a_booking_notifies_the_passenger(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 3, 'available_seats' => 2]);
        $booking = Booking::factory()->for($trip)->create();
        $this->actingAsUser($trip->owner);

        $this->patchJson("/api/bookings/{$booking->id}/cancel")->assertOk();

        $this->assertSame(3, $trip->fresh()->available_seats);
        Notification::assertSentTo($booking->passenger, BookingCancelledNotification::class,
            fn ($n) => $n->cancelledByPassenger === false);
    }

    public function test_booking_cannot_be_cancelled_after_trip_started(): void
    {
        $trip = $this->publishedTrip(['status' => TripStatus::Started]);
        $booking = Booking::factory()->for($trip)->create();
        $this->actingAsUser($booking->passenger);

        $this->patchJson("/api/bookings/{$booking->id}/cancel")
            ->assertUnprocessable()->assertJsonPath('error_code', 'trip_already_started');
    }

    public function test_booking_lists_for_passenger_and_owner(): void
    {
        $trip = $this->publishedTrip();
        $booking = Booking::factory()->for($trip)->create();

        $this->actingAsUser($booking->passenger);
        $this->getJson('/api/bookings')->assertOk()->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.owner_phone', $trip->owner->phone);

        $this->actingAsUser($trip->owner);
        $this->getJson('/api/bookings?role=owner')->assertOk()->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.passenger_phone', $booking->passenger->phone);
        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_booking_list_can_be_split_into_upcoming_and_past_ordered_by_departure(): void
    {
        $passenger = User::factory()->create();
        $later = Booking::factory()->for($this->publishedTrip(['departure_date' => now()->addDays(5)->toDateString()]))->for($passenger, 'passenger')->create();
        $sooner = Booking::factory()->for($this->publishedTrip(['departure_date' => now()->addDay()->toDateString()]))->for($passenger, 'passenger')->create();
        $old = Booking::factory()->for($this->publishedTrip(['status' => TripStatus::Completed, 'departure_date' => now()->subDays(10)->toDateString()]))->for($passenger, 'passenger')->completed()->create();
        $recent = Booking::factory()->for($this->publishedTrip(['status' => TripStatus::Completed, 'departure_date' => now()->subDay()->toDateString()]))->for($passenger, 'passenger')->completed()->create();
        $this->actingAsUser($passenger);

        $upcoming = $this->getJson('/api/bookings?scope=upcoming')->assertOk()->json('data');
        $this->assertSame([$sooner->id, $later->id], array_column($upcoming, 'id'));

        $past = $this->getJson('/api/bookings?scope=past')->assertOk()->json('data');
        $this->assertSame([$recent->id, $old->id], array_column($past, 'id'));
    }

    public function test_phone_numbers_are_hidden_from_unconfirmed_users(): void
    {
        $trip = $this->publishedTrip();
        $this->actingAsUser();

        $this->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.owner_phone')
            ->assertJsonMissingPath('data.owner.phone')
            ->assertJsonMissingPath('data.owner.email');
    }
}
