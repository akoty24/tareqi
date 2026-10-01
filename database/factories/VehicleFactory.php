<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\User;
use Database\Factories\Support\EgyptianData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_type' => fake()->randomElement([VehicleType::Sedan, VehicleType::Sedan, VehicleType::Hatchback, VehicleType::Suv]),
            'model' => fake()->randomElement(EgyptianData::CAR_MODELS).' '.fake()->numberBetween(2010, 2024),
            'color' => fake()->randomElement(EgyptianData::COLORS),
            'plate_number' => EgyptianData::plate(),
        ];
    }

    public function microbus(): static
    {
        return $this->state(fn () => [
            'vehicle_type' => VehicleType::Microbus,
            'model' => 'تويوتا هايس '.fake()->numberBetween(2008, 2022),
        ]);
    }
}
