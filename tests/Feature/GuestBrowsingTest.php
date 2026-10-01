<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_search_and_open_public_trips(): void
    {
        $trip = $this->publishedTrip(['origin' => 'ميت خاقان', 'destination' => 'شبين الكوم']);

        $this->getJson('/api/trips')->assertOk()->assertJsonPath('data.0.id', $trip->id);
        $this->getJson('/api/trips/search?origin=ميت خاقان&destination=شبين الكوم')->assertOk()->assertJsonPath('data.0.id', $trip->id);
        $this->getJson('/api/places?q=ميت')->assertOk();
        $this->getJson("/api/users/{$trip->owner_id}")->assertOk();
        $this->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.is_mine', false)
            ->assertJsonPath('data.my_booking', null)
            ->assertJsonMissingPath('data.owner_phone');
    }

    public function test_guests_cannot_see_drafts_or_act(): void
    {
        $draft = $this->publishedTrip(['status' => TripStatus::Draft]);
        $trip = $this->publishedTrip();

        $this->getJson("/api/trips/{$draft->id}")->assertForbidden();
        $this->getJson('/api/trips?mine=1')->assertUnauthorized();
        $this->postJson("/api/trips/{$trip->id}/bookings", ['seats' => 1])->assertUnauthorized();
        $this->postJson('/api/trips', [])->assertUnauthorized();
    }

    public function test_a_token_still_personalises_public_routes(): void
    {
        $trip = $this->publishedTrip();
        $booking = Booking::factory()->for($trip)->create();
        Sanctum::actingAs($booking->passenger);

        $this->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.my_booking.id', $booking->id)
            ->assertJsonPath('data.owner_phone', $trip->owner->phone);

        // The owner does not see their own trip when browsing.
        Sanctum::actingAs($trip->owner);
        $this->getJson('/api/trips')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_blocked_token_is_rejected_on_public_routes(): void
    {
        Sanctum::actingAs(User::factory()->blocked()->create());

        $this->getJson('/api/trips')->assertForbidden()->assertJsonPath('error_code', 'account_blocked');
    }
}
