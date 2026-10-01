<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ReportStatus;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Report;
use App\Models\User;
use App\Notifications\TripCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportAndAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_a_trip_and_reported_user_is_derived(): void
    {
        $trip = $this->publishedTrip();
        $reporter = $this->actingAsUser();

        $this->postJson('/api/reports', ['trip_id' => $trip->id, 'reason' => 'inappropriate_content', 'description' => 'محتوى غير لائق'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reported_user_id' => $trip->owner_id,
            'trip_id' => $trip->id,
        ]);
    }

    public function test_booking_report_targets_the_other_party_and_requires_participation(): void
    {
        $booking = Booking::factory()->for($this->publishedTrip())->create();

        $this->actingAsUser($booking->trip->owner);
        $this->postJson('/api/reports', ['booking_id' => $booking->id, 'reason' => 'no_show'])->assertCreated();
        $this->assertDatabaseHas('reports', ['reported_user_id' => $booking->passenger_id, 'booking_id' => $booking->id]);

        $this->actingAsUser();
        $this->postJson('/api/reports', ['booking_id' => $booking->id, 'reason' => 'no_show'])->assertForbidden();
    }

    public function test_user_cannot_report_themselves_and_must_specify_target(): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/reports', ['reported_user_id' => $user->id, 'reason' => 'other'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'cannot_report_self');
        $this->postJson('/api/reports', ['reason' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('reported_user_id');
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $this->actingAsUser();

        foreach (['dashboard', 'users', 'trips', 'bookings', 'trip-requests', 'reports'] as $path) {
            $this->getJson("/api/admin/{$path}")->assertForbidden();
        }
    }

    public function test_admin_dashboard_statistics(): void
    {
        $trip = $this->publishedTrip();
        Booking::factory()->for($trip)->pending()->create();
        $this->publishedTrip(['status' => TripStatus::Cancelled]);
        User::factory()->blocked()->create();
        Report::factory()->create(['reason' => 'other']);
        $this->actingAsAdmin();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.trips.total', 2)
            ->assertJsonPath('data.trips.active', 1)
            ->assertJsonPath('data.trips.cancelled', 1)
            ->assertJsonPath('data.bookings.pending', 1)
            ->assertJsonPath('data.users.blocked', 1)
            ->assertJsonPath('data.reports.pending', 1);
    }

    public function test_admin_can_block_and_unblock_users_and_blocking_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('api');
        $this->actingAsAdmin();

        $this->patchJson("/api/admin/users/{$user->id}/block")->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->assertSame(0, $user->tokens()->count());

        $this->patchJson("/api/admin/users/{$user->id}/unblock")->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_admin_cannot_block_another_admin_or_self(): void
    {
        $admin = $this->actingAsAdmin();
        $other = User::factory()->admin()->create();

        $this->patchJson("/api/admin/users/{$other->id}/block")->assertForbidden();
        $this->patchJson("/api/admin/users/{$admin->id}/block")->assertForbidden();
    }

    public function test_blocked_user_existing_session_is_rejected(): void
    {
        $user = User::factory()->blocked()->create();
        $this->actingAsUser($user);

        $this->getJson('/api/auth/me')->assertForbidden()->assertJsonPath('error_code', 'account_blocked');
    }

    public function test_admin_can_search_and_filter_users(): void
    {
        User::factory()->create(['name' => 'زينب الفرماوي']);
        User::factory()->blocked()->create();
        $this->actingAsAdmin();

        $this->getJson('/api/admin/users?search=زينب')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/users?status=blocked')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_cancel_inappropriate_trip_and_everyone_is_notified(): void
    {
        Notification::fake();
        $trip = $this->publishedTrip();
        $booking = Booking::factory()->for($trip)->create();
        $this->actingAsAdmin();

        $this->patchJson("/api/admin/trips/{$trip->id}/cancel", ['reason' => 'مخالف للشروط'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        Notification::assertSentTo([$trip->owner, $booking->passenger], TripCancelledNotification::class, fn ($n) => $n->byAdmin);
    }

    public function test_admin_lists_filter_and_reviews_reports(): void
    {
        Report::factory()->create(['reason' => 'other']);
        $report = Report::factory()->create(['reason' => 'fraud']);
        $admin = $this->actingAsAdmin();

        $this->getJson('/api/admin/reports?reason=fraud')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reporter.id', $report->reporter_id);
        $this->getJson("/api/admin/reports/{$report->id}")->assertOk();

        $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved', 'admin_notes' => 'تم التواصل'])
            ->assertOk()->assertJsonPath('data.status', 'resolved');

        $report->refresh();
        $this->assertSame(ReportStatus::Resolved, $report->status);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
    }

    public function test_admin_lists_trips_bookings_and_requests(): void
    {
        $trip = $this->publishedTrip(['origin' => 'كمشيش']);
        Booking::factory()->for($trip)->pending()->create();
        \App\Models\TripRequest::factory()->create();
        $this->actingAsAdmin();

        $this->getJson('/api/admin/trips?search=كمشيش')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/bookings?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/trip-requests?status=active')->assertOk()->assertJsonCount(1, 'data');
    }
}
