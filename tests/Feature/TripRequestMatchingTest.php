<?php

namespace Tests\Feature;

use App\Enums\TripRequestStatus;
use App\Models\TripRequest;
use App\Models\User;
use App\Notifications\TripRequestMatchedNotification;
use App\Services\Matching\TripRequestMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TripRequestMatchingTest extends TestCase
{
    use RefreshDatabase;

    private function request(array $attributes = [], ?User $user = null): TripRequest
    {
        return TripRequest::factory()->for($user ?? User::factory()->create())->create(array_merge([
            'origin' => 'ميت خاقان',
            'destination' => 'شبين الكوم',
            'requested_date' => now()->addDays(2)->toDateString(),
            'preferred_time_from' => '07:00',
            'preferred_time_to' => '09:00',
            'passengers_count' => 1,
        ], $attributes));
    }

    private function publishTripViaApi(User $driver, array $overrides = [])
    {
        $this->actingAsUser($driver);

        return $this->postJson('/api/trips', array_merge([
            'vehicle_id' => $driver->vehicles->first()->id,
            'origin' => 'ميت خاقان',
            'destination' => 'شبين الكوم',
            'departure_date' => now()->addDays(2)->toDateString(),
            'departure_time' => '08:00',
            'total_seats' => 3,
            'cost_type' => 'free',
        ], $overrides))->assertCreated();
    }

    public function test_user_can_create_list_update_and_cancel_trip_requests(): void
    {
        $user = $this->actingAsUser();
        $payload = [
            'origin' => 'كمشيش', 'destination' => 'طنطا',
            'requested_date' => now()->addDay()->toDateString(),
            'preferred_time_from' => '08:00', 'preferred_time_to' => '10:00', 'passengers_count' => 2,
        ];

        $id = $this->postJson('/api/trip-requests', $payload)
            ->assertCreated()
            ->assertJsonPath('data.trip_request.status', 'active')
            ->json('data.trip_request.id');

        $this->getJson('/api/trip-requests')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->putJson("/api/trip-requests/{$id}", ['passengers_count' => 1])->assertOk()->assertJsonPath('data.passengers_count', 1);
        $this->deleteJson("/api/trip-requests/{$id}")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->putJson("/api/trip-requests/{$id}", ['passengers_count' => 2])->assertUnprocessable();

        $this->actingAsUser();
        $this->getJson("/api/trip-requests/{$id}")->assertForbidden();
    }

    public function test_trip_request_validation(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/trip-requests', [
            'origin' => '', 'requested_date' => now()->subDay()->toDateString(),
            'preferred_time_from' => '10:00', 'preferred_time_to' => '08:00', 'passengers_count' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['origin', 'destination', 'requested_date', 'preferred_time_to', 'passengers_count']);
    }

    public function test_creating_request_returns_existing_matching_trips(): void
    {
        $trip = $this->publishedTrip(['origin' => 'ميت خاقان', 'destination' => 'شبين الكوم', 'departure_date' => now()->addDays(2)->toDateString(), 'departure_time' => '08:00']);
        $this->publishedTrip(['origin' => 'البتانون', 'destination' => 'طنطا', 'departure_date' => now()->addDays(2)->toDateString()]);
        $this->actingAsUser();

        $this->postJson('/api/trip-requests', [
            'origin' => 'ميت خاقان', 'destination' => 'شبين الكوم',
            'requested_date' => now()->addDays(2)->toDateString(), 'passengers_count' => 1,
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.matching_trips')
            ->assertJsonPath('data.matching_trips.0.id', $trip->id);
    }

    public function test_publishing_a_matching_trip_notifies_the_requester_once(): void
    {
        Notification::fake();
        $request = $this->request();
        $unrelated = $this->request(['origin' => 'البتانون', 'destination' => 'طنطا']);
        $otherDay = $this->request(['requested_date' => now()->addDays(4)->toDateString()]);
        $driver = $this->driver();

        $tripId = $this->publishTripViaApi($driver)->json('data.id');

        Notification::assertSentTo($request->user, TripRequestMatchedNotification::class,
            fn ($n) => $n->trip->id === $tripId && $n->tripRequest->is($request));
        Notification::assertNotSentTo($unrelated->user, TripRequestMatchedNotification::class);
        Notification::assertNotSentTo($otherDay->user, TripRequestMatchedNotification::class);
        $this->assertDatabaseHas('trip_request_matches', ['trip_request_id' => $request->id, 'trip_id' => $tripId]);

        // Running the matcher again must not notify a second time.
        app(TripRequestMatchingService::class)->matchTrip(\App\Models\Trip::find($tripId));
        Notification::assertSentToTimes($request->user, TripRequestMatchedNotification::class, 1);

        // Matching never creates bookings.
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_no_match_when_not_enough_seats_or_own_request(): void
    {
        Notification::fake();
        $driver = $this->driver();
        $big = $this->request(['passengers_count' => 4]);
        $own = $this->request([], $driver);

        $this->publishTripViaApi($driver, ['total_seats' => 3]);

        Notification::assertNotSentTo($big->user, TripRequestMatchedNotification::class);
        Notification::assertNotSentTo($driver, TripRequestMatchedNotification::class);
        $this->assertSame(TripRequestStatus::Active, $own->fresh()->status);
    }

    public function test_draft_trip_does_not_trigger_matching_until_published(): void
    {
        Notification::fake();
        $request = $this->request();
        $driver = $this->driver();

        $id = $this->publishTripViaApi($driver, ['publish' => false])->json('data.id');
        Notification::assertNothingSent();

        $this->patchJson("/api/trips/{$id}/publish")->assertOk();
        Notification::assertSentTo($request->user, TripRequestMatchedNotification::class);
    }

    public function test_booking_a_matched_trip_fulfills_the_request(): void
    {
        $request = $this->request();
        $driver = $this->driver();
        $tripId = $this->publishTripViaApi($driver)->json('data.id');

        $this->actingAsUser($request->user);
        $this->postJson("/api/trips/{$tripId}/bookings", ['seats' => 1])->assertCreated();

        $this->assertSame(TripRequestStatus::Fulfilled, $request->fresh()->status);
    }

    public function test_expire_job_marks_old_requests_expired(): void
    {
        $old = $this->request(['requested_date' => now()->addDay()->toDateString()]);
        $this->travel(3)->days();

        (new \App\Jobs\ExpireTripRequests)->handle();

        $this->assertSame(TripRequestStatus::Expired, $old->fresh()->status);
    }
}
