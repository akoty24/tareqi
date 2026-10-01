<?php

namespace Tests;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsUser(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsAdmin(): User
    {
        return $this->actingAsUser(User::factory()->admin()->create());
    }

    /** A user with a vehicle, ready to create trips. */
    protected function driver(): User
    {
        $user = User::factory()->create();
        Vehicle::factory()->for($user)->create();

        return $user->load('vehicles');
    }

    /** Published trip departing tomorrow at 08:00 by default. */
    protected function publishedTrip(array $attributes = [], ?User $owner = null): Trip
    {
        $owner ??= $this->driver();

        return Trip::factory()
            ->for($owner, 'owner')
            ->state(['vehicle_id' => $owner->vehicles()->value('id') ?? Vehicle::factory()->for($owner)->create()->id])
            ->departingAt(now()->addDay()->toDateString(), '08:00')
            ->create($attributes);
    }
}
