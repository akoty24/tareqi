<?php

namespace Tests\Unit;

use App\Enums\CostType;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\TripMatchingService;
use Tests\TestCase;

/** Pure scoring rules, no database. */
class TripMatchingServiceTest extends TestCase
{
    private TripMatchingService $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new TripMatchingService;
    }

    private function trip(array $attributes = []): Trip
    {
        $trip = new Trip;
        $trip->forceFill(array_merge([
            'origin' => 'ميت خاقان',
            'destination' => 'شبين الكوم',
            'departure_date' => '2026-10-10',
            'departure_time' => '08:00',
            'total_seats' => 4,
            'available_seats' => 3,
            'cost_type' => CostType::Free,
            'status' => TripStatus::Published,
        ], $attributes));

        return $trip;
    }

    public function test_perfect_match_scores_100(): void
    {
        $result = $this->matcher->score($this->trip(), new MatchCriteria(
            origin: 'ميت خاقان', destination: 'شبين الكوم', date: '2026-10-10',
            timeFrom: '07:00', timeTo: '09:00', passengers: 2,
        ));

        $this->assertSame(100, $result['score']);
        $this->assertSame(['origin' => 30, 'destination' => 30, 'date' => 20, 'time' => 15, 'availability' => 5], $result['breakdown']);
    }

    public function test_partial_place_match_gives_half_points(): void
    {
        $result = $this->matcher->score($this->trip(['destination' => 'القاهرة - رمسيس']), new MatchCriteria(destination: 'القاهرة'));

        $this->assertSame(15, $result['breakdown']['destination']);
    }

    public function test_different_place_gives_zero(): void
    {
        $result = $this->matcher->score($this->trip(), new MatchCriteria(origin: 'طنطا'));

        $this->assertSame(0, $result['breakdown']['origin']);
    }

    public function test_date_scoring(): void
    {
        $trip = $this->trip();

        $this->assertSame(20, $this->matcher->score($trip, new MatchCriteria(date: '2026-10-10'))['breakdown']['date']);
        $this->assertSame(10, $this->matcher->score($trip, new MatchCriteria(date: '2026-10-11'))['breakdown']['date']);
        $this->assertSame(0, $this->matcher->score($trip, new MatchCriteria(date: '2026-10-13'))['breakdown']['date']);
    }

    public function test_time_proximity_decays_linearly_over_three_hours(): void
    {
        $trip = $this->trip(['departure_time' => '08:00']);
        $score = fn (string $from, string $to) => $this->matcher->score($trip, new MatchCriteria(timeFrom: $from, timeTo: $to))['breakdown']['time'];

        $this->assertSame(15, $score('07:00', '09:00'));   // inside window
        $this->assertSame(8, $score('09:30', '10:00'));    // 90 min away -> half
        $this->assertSame(0, $score('11:00', '12:00'));    // 3h away -> 0
        $this->assertSame(0, $score('14:00', '15:00'));
    }

    public function test_availability_points_require_enough_seats(): void
    {
        $trip = $this->trip(['available_seats' => 1]);

        $this->assertSame(5, $this->matcher->score($trip, new MatchCriteria(passengers: 1))['breakdown']['availability']);
        $this->assertSame(0, $this->matcher->score($trip, new MatchCriteria(passengers: 2))['breakdown']['availability']);
    }

    public function test_unspecified_criteria_award_full_points(): void
    {
        $this->assertSame(100, $this->matcher->score($this->trip(), new MatchCriteria)['score']);
    }
}
