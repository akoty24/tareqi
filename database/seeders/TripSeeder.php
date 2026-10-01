<?php

namespace Database\Seeders;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Database\Factories\Support\EgyptianData;
use Illuminate\Database\Seeder;

/**
 * Upcoming outbound trips (half with return trips), plus drafts, a cancelled
 * trip and completed trips in the past (used for bookings/ratings history).
 */
class TripSeeder extends Seeder
{
    public function run(): void
    {
        $owners = User::whereHas('vehicles')->with('vehicles')->get();

        foreach ($owners as $owner) {
            /** @var Vehicle $car */
            $car = $owner->vehicles->first();

            // 2 upcoming outbound trips, the first one with a return trip.
            for ($i = 0; $i < 2; $i++) {
                $outbound = Trip::factory()
                    ->for($owner, 'owner')
                    ->state(['vehicle_id' => $car->id])
                    ->route(fake()->randomElement(EgyptianData::VILLAGES), fake()->randomElement(EgyptianData::CITIES))
                    ->departingAt(now()->addDays(fake()->numberBetween(1, 7))->toDateString(), sprintf('%02d:%s', fake()->numberBetween(6, 9), fake()->randomElement(['00', '30'])))
                    ->create();

                if ($i === 0) {
                    Trip::factory()->returnOf($outbound, sprintf('%02d:00', fake()->numberBetween(15, 20)))->create([
                        'total_seats' => $outbound->total_seats,
                        'available_seats' => $outbound->total_seats,
                        'cost_type' => $outbound->cost_type,
                        'price_per_seat' => $outbound->price_per_seat,
                        'estimated_cost_per_passenger' => $outbound->estimated_cost_per_passenger,
                    ]);
                }
            }

            // Past, completed trip.
            Trip::factory()->for($owner, 'owner')->state(['vehicle_id' => $car->id])->completed()->create();
        }

        $driver = User::where('email', 'driver@mishwar.test')->with('vehicles')->firstOrFail();
        $microbus = $driver->vehicles->last();

        // Well-known demo trips for the driver account.
        $daily = Trip::factory()->for($driver, 'owner')
            ->state(['vehicle_id' => $driver->vehicles->first()->id])
            ->route('ميت خاقان', 'شبين الكوم')
            ->departingAt(now()->addDay()->toDateString(), '07:30')
            ->fixedPrice(25)->seats(4)->autoConfirm()
            ->create(['notes' => 'رحلة يومية للموظفين. التجمع أمام المسجد الكبير.']);
        Trip::factory()->returnOf($daily, '16:30')->fixedPrice(25)->seats(4)->autoConfirm()->create();

        Trip::factory()->for($driver, 'owner')
            ->state(['vehicle_id' => $microbus->id])
            ->route('ميت خاقان', 'القاهرة - رمسيس')
            ->departingAt(now()->addDays(3)->toDateString(), '06:00')
            ->costSharing(80)->seats(12)->autoConfirm(false)
            ->create();

        Trip::factory()->for($driver, 'owner')->state(['vehicle_id' => $microbus->id])
            ->route('ميت خاقان', 'طنطا')->free()->draft()->create();

        Trip::factory()->for($driver, 'owner')->state(['vehicle_id' => $driver->vehicles->first()->id])
            ->route('ميت خاقان', 'بنها')->cancelled()->create(['cancellation_reason' => 'عطل في العربية']);

        Trip::factory()->count(2)->for($driver, 'owner')
            ->state(['vehicle_id' => $driver->vehicles->first()->id])
            ->route('ميت خاقان', 'شبين الكوم')->fixedPrice(25)->completed()->create();

        // A trip that is already FULL (seats are set in BookingSeeder).
        $owner = $owners->first();
        Trip::factory()->for($owner, 'owner')->state(['vehicle_id' => $owner->vehicles->first()->id])
            ->route('سرس الليان', 'منوف')->seats(2)->status(TripStatus::Published)
            ->create(['notes' => 'full-demo']);
    }
}
