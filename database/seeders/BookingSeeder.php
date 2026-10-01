<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Factories do not touch seat counters, so after creating bookings this
 * seeder recalculates available_seats / FULL status exactly like the
 * application would.
 */
class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $passengers = User::query()
            ->where('role', UserRole::User)
            ->where('status', UserStatus::Active)
            ->get();
        $sara = $passengers->firstWhere('email', 'passenger@mishwar.test');

        // Upcoming published trips: a few confirmed / pending bookings.
        Trip::query()->where('status', TripStatus::Published)->get()->each(function (Trip $trip) use ($passengers) {
            $candidates = $this->otherThan($passengers, $trip)->shuffle();
            $seatsLeft = $trip->total_seats - 1; // keep at least one seat free by default
            if ($trip->notes === 'full-demo') {
                $seatsLeft = $trip->total_seats;
            }

            foreach ($candidates->take(fake()->numberBetween(0, 3)) as $passenger) {
                if ($seatsLeft <= 0) {
                    break;
                }
                $seats = min($seatsLeft, $trip->notes === 'full-demo' ? $trip->total_seats : fake()->numberBetween(1, 2));
                $status = $trip->auto_confirm_bookings || fake()->boolean(60) ? BookingStatus::Confirmed : BookingStatus::Pending;

                Booking::factory()->for($trip)->for($passenger, 'passenger')->status($status)->create(['seats' => $seats]);
                $seatsLeft -= $seats;
            }
        });

        // The demo passenger always has an upcoming booking on the driver's daily trip.
        $daily = Trip::query()->whereHas('owner', fn ($q) => $q->where('email', 'driver@mishwar.test'))
            ->where('origin', 'ميت خاقان')->where('destination', 'شبين الكوم')
            ->where('status', TripStatus::Published)->whereNull('parent_trip_id')->first();
        if ($daily && ! $daily->bookings()->where('passenger_id', $sara->id)->exists()) {
            Booking::factory()->for($daily)->for($sara, 'passenger')->create(['seats' => 1]);
        }

        // Completed trips: completed bookings (these can be rated).
        Trip::query()->where('status', TripStatus::Completed)->get()->each(function (Trip $trip) use ($passengers, $sara) {
            $candidates = $this->otherThan($passengers, $trip)->shuffle()->take(min(2, $trip->total_seats));
            if ($trip->owner->email === 'driver@mishwar.test' && ! $candidates->contains('id', $sara->id)) {
                $candidates = $candidates->take(1)->push($sara);
            }
            foreach ($candidates as $passenger) {
                Booking::factory()->for($trip)->for($passenger, 'passenger')->completed()->create();
            }
        });

        $this->recalculateSeats();
    }

    private function otherThan(Collection $passengers, Trip $trip): Collection
    {
        return $passengers->where('id', '!=', $trip->owner_id)->values();
    }

    private function recalculateSeats(): void
    {
        Trip::query()->withSum(['bookings as held_seats' => fn ($q) => $q->whereIn('status', BookingStatus::active())], 'seats')
            ->get()
            ->each(function (Trip $trip) {
                $trip->available_seats = max(0, $trip->total_seats - (int) $trip->held_seats);
                if ($trip->status === TripStatus::Published && $trip->available_seats === 0) {
                    $trip->status = TripStatus::Full;
                }
                if ($trip->notes === 'full-demo') {
                    $trip->notes = 'رحلة مكتملة العدد.';
                }
                unset($trip->held_seats);
                $trip->save();
            });
    }
}
