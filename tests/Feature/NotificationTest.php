<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Jobs\SendTripReminders;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripApproachingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_notifications_are_stored_listed_and_marked_read(): void
    {
        $trip = $this->publishedTrip(['auto_confirm_bookings' => false]);
        $passenger = $this->actingAsUser();

        // Real (not faked) notifications: queue is sync in tests.
        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])->assertCreated();

        $this->actingAsUser($trip->owner);
        $response = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.type', 'new_booking_request')
            ->assertJsonPath('data.0.link', "/trips/{$trip->id}");
        $this->assertNotEmpty($response->json('data.0.message'));

        $id = $response->json('data.0.id');
        $this->patchJson("/api/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/notifications?unread=1')->assertJsonCount(0, 'data');

        // A user cannot touch someone else's notification.
        $this->actingAsUser($passenger);
        $this->patchJson("/api/notifications/{$id}/read")->assertNotFound();
        $this->patchJson('/api/notifications/read-all')->assertOk();
        $this->assertSame(0, $passenger->unreadNotifications()->count());
    }

    public function test_notification_text_uses_readable_arabic_date_time_and_seat_counts(): void
    {
        $trip = $this->publishedTrip(['origin' => 'ميت خاقان', 'destination' => 'شبين الكوم', 'departure_date' => '2026-10-06', 'departure_time' => '07:30']);
        $booking = Booking::factory()->for($trip)->create(['seats' => 2]);

        $message = (new \App\Notifications\BookingCreatedNotification($booking))->message();

        $this->assertStringContainsString('مقعدين', $message);
        $this->assertStringContainsString('الثلاثاء 6 أكتوبر', $message);
        $this->assertStringContainsString('7:30 ص', $message);
        $this->assertStringNotContainsString('2026-10-06', $message);
    }

    public function test_reminders_are_sent_once_to_owner_and_confirmed_passengers(): void
    {
        Notification::fake();
        $trip = $this->publishedTrip();
        $confirmed = Booking::factory()->for($trip)->create();
        $pending = Booking::factory()->for($trip)->pending()->create();

        $this->travelTo($trip->departureAt()->subHours(2));
        (new SendTripReminders)->handle();
        (new SendTripReminders)->handle();

        Notification::assertSentToTimes($trip->owner, TripApproachingNotification::class, 1);
        Notification::assertSentToTimes($confirmed->passenger, TripApproachingNotification::class, 1);
        Notification::assertNotSentTo($pending->passenger, TripApproachingNotification::class);
        $this->assertNotNull($trip->fresh()->reminder_sent_at);
    }

    public function test_return_trip_reminder_uses_return_trip_type(): void
    {
        Notification::fake();
        $outbound = $this->publishedTrip();
        $return = Trip::factory()->returnOf($outbound, '18:00')->create();
        $booking = Booking::factory()->for($return)->create();

        $this->travelTo($return->departureAt()->subHour());
        (new SendTripReminders)->handle();

        Notification::assertSentTo($booking->passenger, TripApproachingNotification::class,
            fn ($n) => $n->type() === 'return_trip_approaching');
    }

    public function test_reminders_are_not_sent_for_far_or_cancelled_trips(): void
    {
        Notification::fake();
        $this->publishedTrip(['departure_date' => now()->addDays(3)->toDateString()]);
        $cancelled = $this->publishedTrip(['status' => TripStatus::Cancelled]);

        $this->travelTo($cancelled->departureAt()->subHour());
        (new SendTripReminders)->handle();

        Notification::assertNothingSent();
    }

    public function test_notification_channels_include_mail_only_where_appropriate(): void
    {
        $user = User::factory()->create();
        $trip = $this->publishedTrip();

        $this->assertSame(['database', 'mail'], (new TripApproachingNotification($trip))->via($user));
        $this->assertSame(['database'], (new \App\Notifications\BookingRejectedNotification(
            Booking::factory()->for($trip)->create()
        ))->via($user));
    }
}
