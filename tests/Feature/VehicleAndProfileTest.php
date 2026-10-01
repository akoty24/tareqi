<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_manages_own_vehicles(): void
    {
        $this->actingAsUser();

        $id = $this->postJson('/api/vehicles', [
            'vehicle_type' => 'sedan', 'model' => 'نيسان صني 2018', 'color' => 'أبيض', 'plate_number' => 'ن ص ر 4521',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/vehicles')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/vehicles/{$id}", ['color' => 'أسود'])->assertOk()->assertJsonPath('data.color', 'أسود');
        $this->deleteJson("/api/vehicles/{$id}")->assertOk();
        $this->assertSoftDeleted('vehicles', ['id' => $id]);
    }

    public function test_vehicle_validation(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/vehicles', ['vehicle_type' => 'rocket'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_type', 'model', 'color', 'plate_number']);
    }

    public function test_user_cannot_modify_another_users_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->actingAsUser();

        $this->putJson("/api/vehicles/{$vehicle->id}", ['color' => 'أحمر'])->assertForbidden();
        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertForbidden();
    }

    public function test_vehicle_used_by_upcoming_trip_cannot_be_deleted(): void
    {
        $trip = $this->publishedTrip();
        $this->actingAsUser($trip->owner);

        $this->deleteJson("/api/vehicles/{$trip->vehicle_id}")
            ->assertStatus(409)->assertJsonPath('error_code', 'vehicle_in_use');
    }

    public function test_profile_show_and_update(): void
    {
        User::factory()->create(['phone' => '01099999999']);
        $user = $this->actingAsUser();

        $this->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');

        $this->putJson('/api/profile', ['phone' => '01099999999'])->assertJsonValidationErrors('phone');
        $this->putJson('/api/profile', ['name' => 'اسم جديد', 'email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'اسم جديد')
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/profile/password', [
            'current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertJsonValidationErrors('current_password');

        $this->withToken($token)->putJson('/api/profile/password', [
            'current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertOk();
    }

    public function test_profile_photo_upload(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $url = $this->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->create('me.jpg', 50, 'image/jpeg')])
            ->assertOk()->json('data.profile_photo_url');

        $this->assertNotNull($url);
        $this->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 10)])
            ->assertJsonValidationErrors('photo');
    }
}
