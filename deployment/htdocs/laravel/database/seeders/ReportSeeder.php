<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Booking;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@mishwar.test')->firstOrFail();
        $blocked = User::where('email', 'blocked@mishwar.test')->firstOrFail();

        // Reports tied to real completed bookings (passenger reports the owner).
        Booking::query()->where('status', BookingStatus::Completed)->with('trip')->inRandomOrder()->limit(3)->get()
            ->each(fn (Booking $booking) => Report::factory()->create([
                'reporter_id' => $booking->passenger_id,
                'reported_user_id' => $booking->trip->owner_id,
                'trip_id' => $booking->trip_id,
                'booking_id' => $booking->id,
            ]));

        // The blocked account was reported and the report resolved.
        Report::factory()->status(ReportStatus::Resolved)->create([
            'reporter_id' => User::where('email', 'passenger@mishwar.test')->value('id'),
            'reported_user_id' => $blocked->id,
            'reason' => ReportReason::Harassment,
            'admin_notes' => 'تم التحقق وإيقاف الحساب.',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subDay(),
        ]);

        Report::factory()->status(ReportStatus::UnderReview)->create([
            'reporter_id' => User::where('email', 'driver@mishwar.test')->value('id'),
            'reported_user_id' => User::members()->where('email', 'not like', '%@mishwar.test')->value('id'),
            'reason' => ReportReason::NoShow,
        ]);
    }
}
