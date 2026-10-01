<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $driver = User::where('email', 'driver@mishwar.test')->firstOrFail();
        Vehicle::factory()->for($driver)->create(['model' => 'هيونداي إلنترا 2019', 'color' => 'فضي']);
        Vehicle::factory()->microbus()->for($driver)->create(['color' => 'أبيض']);

        // About half of the community members own a car.
        User::query()
            ->members()
            ->where('status', UserStatus::Active)
            ->whereNotIn('email', ['driver@mishwar.test', 'passenger@mishwar.test'])
            ->inRandomOrder()
            ->limit(7)
            ->get()
            ->each(fn (User $user) => Vehicle::factory()->for($user)->create());
    }
}
