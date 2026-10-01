<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'محمد أحمد',
            'phone' => '01012345678',
            'email' => 'mohamed@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $overrides);
    }

    public function test_user_can_register_and_receives_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->registrationData());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'mohamed@example.com')
            ->assertJsonPath('data.user.role', null)
            ->assertJsonPath('data.user.permissions', [])
            ->assertJsonStructure(['data' => ['token']])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'mohamed@example.com')->first();
        $this->assertTrue($user->isActive());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_validates_egyptian_phone_and_unique_fields(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', $this->registrationData([
            'phone' => '12345',
            'email' => 'taken@example.com',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['phone', 'email']);
    }

    public function test_registration_cannot_mass_assign_admin_role(): void
    {
        $this->postJson('/api/auth/register', $this->registrationData(['role' => 'admin', 'role_id' => 1, 'status' => 'blocked']))
            ->assertCreated();

        $user = User::where('email', 'mohamed@example.com')->first();
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isActive());
    }

    public function test_user_can_login_with_email_or_phone(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'phone' => '01112223334']);

        $this->postJson('/api/auth/login', ['login' => 'a@example.com', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);

        $this->postJson('/api/auth/login', ['login' => '0111 222 3334', 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/auth/login', ['login' => 'a@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'invalid_credentials');
    }

    public function test_blocked_user_cannot_login(): void
    {
        User::factory()->blocked()->create(['email' => 'b@example.com']);

        $this->postJson('/api/auth/login', ['login' => 'b@example.com', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'account_blocked');
    }

    public function test_me_returns_authenticated_user_and_requires_auth(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized()->assertJsonPath('error_code', 'unauthenticated');

        $user = $this->actingAsUser();
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.completed_trips_as_owner', 0);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        $token = $this->postJson('/api/auth/login', ['login' => 'a@example.com', 'password' => 'password'])->json('data.token');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();
        // Unknown emails get the same response (no account enumeration).
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['login' => 'reset@example.com', 'password' => 'newpass123'])->assertOk();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['login' => 'a@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/auth/login', ['login' => 'a@example.com', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'too_many_requests');
    }
}
