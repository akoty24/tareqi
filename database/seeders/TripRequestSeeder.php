<?php

namespace Database\Seeders;

use App\Enums\TripRequestStatus;
use App\Enums\UserStatus;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class TripRequestSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->members()
            ->where('status', UserStatus::Active)
            ->get();

        foreach ($users->random(min(8, $users->count())) as $user) {
            TripRequest::factory()->for($user)->create();
        }

        $sara = $users->firstWhere('email', 'passenger@mishwar.test');
        TripRequest::factory()->for($sara)->create([
            'origin' => 'ميت خاقان',
            'destination' => 'الإسكندرية',
            'requested_date' => now()->addDays(5)->toDateString(),
            'preferred_time_from' => '07:00',
            'preferred_time_to' => '10:00',
            'passengers_count' => 2,
            'notes' => 'رايحة أنا ووالدتي',
        ]);
        TripRequest::factory()->for($sara)->expired()->create();
        TripRequest::factory()->for($users->random())->status(TripRequestStatus::Fulfilled)->create();
        TripRequest::factory()->for($users->random())->status(TripRequestStatus::Cancelled)->create();
    }
}
