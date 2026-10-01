<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory(),
            'trip_id' => null,
            'booking_id' => null,
            'reason' => fake()->randomElement(ReportReason::cases()),
            'description' => fake()->randomElement([
                'السواق كان بيسوق بسرعة جدًا على الطريق الزراعي.',
                'الراكب حجز ومجاش ولا رد على التليفون.',
                'طلب فلوس أكتر من المتفق عليه.',
                'أسلوب غير محترم في الكلام.',
            ]),
            'status' => ReportStatus::Pending,
        ];
    }

    public function status(ReportStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
