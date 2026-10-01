<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development data only. Schema comes exclusively from migrations:
 *   php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            VehicleSeeder::class,
            TripSeeder::class,
            BookingSeeder::class,
            RatingSeeder::class,
            TripRequestSeeder::class,
            ReportSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
