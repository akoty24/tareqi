<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\TripRequestStatus;
use App\Enums\TripStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Rating;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingCancelledNotification;
use App\Services\RatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cancels_a_booking_frees_seats_and_notifies_both_parties(): void
    {
        Notification::fake();
        $trip = $this->publishedTrip(['total_seats' => 3, 'available_seats' => 1]);
        $booking = Booking::factory()->for($trip)->create(['seats' => 2]);
        $this->actingAsAdmin();

        $this->patchJson("/api/admin/bookings/{$booking->id}/cancel", ['reason' => 'حجز وهمي'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(3, $trip->fresh()->available_seats);
        Notification::assertSentTo($booking->passenger, BookingCancelledNotification::class, fn ($n) => $n->type() === 'booking_cancelled_by_admin');
        Notification::assertSentTo($trip->owner, BookingCancelledNotification::class, fn ($n) => $n->type() === 'booking_cancelled_by_admin');
        $this->assertTrue(ActivityLog::where('action', 'booking.cancelled')->where('subject_id', $booking->id)->exists());
    }

    public function test_admin_closes_an_active_trip_request(): void
    {
        $request = TripRequest::factory()->create();
        $this->actingAsAdmin();

        $this->patchJson("/api/admin/trip-requests/{$request->id}/cancel")->assertOk();
        $this->assertSame(TripRequestStatus::Cancelled, $request->fresh()->status);

        $this->patchJson("/api/admin/trip-requests/{$request->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'trip_request_not_active');
    }

    public function test_admin_lists_and_deletes_vehicles_not_in_use(): void
    {
        $this->actingAsAdmin();
        $trip = $this->publishedTrip();
        $busy = $trip->vehicle;
        $idle = Vehicle::factory()->create(['plate_number' => 'س ع د 123']);

        $this->getJson('/api/admin/vehicles?search=123')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.owner.id', $idle->user_id);

        $this->deleteJson("/api/admin/vehicles/{$busy->id}")->assertStatus(409);
        $this->deleteJson("/api/admin/vehicles/{$idle->id}")->assertOk();
        $this->assertSoftDeleted($idle);
        $this->getJson('/api/admin/vehicles?deleted=1')->assertJsonCount(1, 'data');
    }

    public function test_admin_deletes_an_abusive_rating_and_average_is_recalculated(): void
    {
        $this->actingAsAdmin();
        $driver = User::factory()->create();
        $trip = $this->publishedTrip(['status' => TripStatus::Completed], $this->ownerWithVehicle($driver));
        $good = Rating::factory()->create(['rated_user_id' => $driver->id, 'trip_id' => $trip->id, 'stars' => 5]);
        $bad = Rating::factory()->create(['rated_user_id' => $driver->id, 'trip_id' => $trip->id, 'stars' => 1, 'review' => 'كلام مسيء']);
        app(RatingService::class)->recalculate($driver->id);
        $this->assertEquals(3.0, (float) $driver->fresh()->rating_average);

        $this->getJson('/api/admin/ratings?stars=1')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/admin/ratings/{$bad->id}")->assertOk();

        $this->assertModelMissing($bad);
        $this->assertModelExists($good);
        $this->assertEquals(5.0, (float) $driver->fresh()->rating_average);
        $this->assertSame(1, $driver->fresh()->ratings_count);
    }

    public function test_admin_can_log_a_user_out_of_every_device(): void
    {
        $this->actingAsAdmin();
        $member = User::factory()->create();
        $member->createToken('phone');
        $member->deviceTokens()->create(['token' => 'fcm-token-1']);

        $this->deleteJson("/api/admin/users/{$member->id}/sessions")->assertOk();

        $this->assertSame(0, $member->tokens()->count());
        $this->assertSame(0, $member->deviceTokens()->count());
    }

    public function test_activity_log_records_staff_actions(): void
    {
        $admin = $this->actingAsAdmin();
        $member = User::factory()->create(['name' => 'عمرو دياب']);

        $this->patchJson("/api/admin/users/{$member->id}/block")->assertOk();

        $this->getJson('/api/admin/activity?action=user')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'user.blocked')
            ->assertJsonPath('data.0.causer.id', $admin->id)
            ->assertJsonPath('data.0.description', 'Blocked عمرو دياب');

        $this->getJson('/api/admin/activity', ['Accept-Language' => 'ar'])
            ->assertJsonPath('data.0.description', 'إيقاف حساب عمرو دياب');
    }

    public function test_dashboard_includes_staff_vehicle_and_rating_stats(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.staff', 1)
            ->assertJsonStructure(['data' => ['vehicles', 'ratings' => ['total', 'average'], 'new_users_this_week']]);
    }

    private function ownerWithVehicle(User $user): User
    {
        Vehicle::factory()->for($user)->create();

        return $user->load('vehicles');
    }
}
