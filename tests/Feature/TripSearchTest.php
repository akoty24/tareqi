<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TripSearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(array $params)
    {
        return $this->getJson('/api/trips/search?'.http_build_query($params));
    }

    public function test_search_filters_by_route_date_and_seats(): void
    {
        $date = now()->addDays(2)->toDateString();
        $match = $this->publishedTrip(['origin' => 'ميت خاقان', 'destination' => 'شبين الكوم', 'departure_date' => $date, 'total_seats' => 3, 'available_seats' => 3]);
        $this->publishedTrip(['origin' => 'ميت خاقان', 'destination' => 'طنطا', 'departure_date' => $date]);
        $this->publishedTrip(['origin' => 'ميت خاقان', 'destination' => 'شبين الكوم', 'departure_date' => now()->addDays(5)->toDateString()]);
        $this->publishedTrip(['origin' => 'ميت خاقان', 'destination' => 'شبين الكوم', 'departure_date' => $date, 'total_seats' => 1, 'available_seats' => 1]);
        $this->actingAsUser();

        $this->search(['origin' => 'ميت خاقان', 'destination' => 'شبين الكوم', 'date' => $date, 'passengers' => 2])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.match.score', 100)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_search_excludes_unbookable_past_and_own_trips(): void
    {
        $date = now()->addDay()->toDateString();
        $route = ['origin' => 'البتانون', 'destination' => 'بنها', 'departure_date' => $date];
        $ok = $this->publishedTrip($route);
        $this->publishedTrip($route + ['status' => TripStatus::Full]);
        $this->publishedTrip($route + ['status' => TripStatus::Cancelled]);
        $this->publishedTrip($route + ['status' => TripStatus::Draft]);
        $this->publishedTrip(['departure_date' => now()->subDay()->toDateString()] + $route);
        $viewer = $this->driver();
        $this->publishedTrip($route, $viewer);
        $this->actingAsUser($viewer);

        $this->search(['origin' => 'البتانون', 'destination' => 'بنها'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ok->id);
    }

    public function test_search_filters_time_range_and_cost_type(): void
    {
        $date = now()->addDay()->toDateString();
        $morning = $this->publishedTrip(['departure_date' => $date, 'departure_time' => '07:00', 'cost_type' => 'free', 'price_per_seat' => null, 'estimated_cost_per_passenger' => null]);
        $this->publishedTrip(['departure_date' => $date, 'departure_time' => '19:00', 'cost_type' => 'free', 'price_per_seat' => null, 'estimated_cost_per_passenger' => null]);
        $this->publishedTrip(['departure_date' => $date, 'departure_time' => '07:30', 'cost_type' => 'fixed_price', 'price_per_seat' => 50, 'estimated_cost_per_passenger' => null]);
        $this->actingAsUser();

        $this->search(['date' => $date, 'time_from' => '06:00', 'time_to' => '09:00', 'cost_type' => 'free'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $morning->id);
    }

    public function test_arabic_spelling_variants_match(): void
    {
        $trip = $this->publishedTrip(['origin' => 'ميت أبو الكوم', 'destination' => 'الإسكندرية']);
        $this->actingAsUser();

        $this->search(['origin' => 'ميت ابو الكوم', 'destination' => 'الاسكندريه'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $trip->id)
            ->assertJsonPath('data.0.match.breakdown.origin', 30)
            ->assertJsonPath('data.0.match.breakdown.destination', 30);
    }

    public function test_results_are_ordered_by_match_score(): void
    {
        $date = now()->addDays(2)->toDateString();
        // Partial destination match ("القاهرة - رمسيس" contains "القاهرة") departing far from preferred time.
        $weak = $this->publishedTrip(['origin' => 'كمشيش', 'destination' => 'القاهرة - رمسيس', 'departure_date' => $date, 'departure_time' => '06:00']);
        // Exact destination and inside the time window.
        $strong = $this->publishedTrip(['origin' => 'كمشيش', 'destination' => 'القاهرة', 'departure_date' => $date, 'departure_time' => '09:00']);
        $this->actingAsUser();

        $response = $this->search(['origin' => 'كمشيش', 'destination' => 'القاهرة', 'date' => $date, 'time_from' => '05:00', 'time_to' => '12:00'])->assertOk();

        $this->assertSame([$strong->id, $weak->id], array_column($response->json('data'), 'id'));
        $this->assertGreaterThan($response->json('data.1.match.score'), $response->json('data.0.match.score'));
    }

    public function test_search_is_paginated(): void
    {
        foreach (range(1, 5) as $_) {
            $this->publishedTrip(['origin' => 'الراهب', 'destination' => 'منوف']);
        }
        $this->actingAsUser();

        $this->search(['origin' => 'الراهب', 'per_page' => 2, 'page' => 2])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_search_does_not_run_n_plus_one_queries(): void
    {
        foreach (range(1, 8) as $_) {
            $this->publishedTrip(['origin' => 'سرس الليان', 'destination' => 'شبين الكوم']);
        }
        $this->actingAsUser();

        DB::enableQueryLog();
        $this->search(['origin' => 'سرس الليان'])->assertOk()->assertJsonCount(8, 'data');
        $queries = count(DB::getQueryLog());

        // 1 trips query + owners + vehicles eager loads (+ auth/token lookups).
        $this->assertLessThanOrEqual(6, $queries);
    }

    public function test_search_validates_input(): void
    {
        $this->actingAsUser();
        $this->search(['date' => 'tomorrow', 'passengers' => 0, 'cost_type' => 'cheap'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'passengers', 'cost_type']);
    }
}
