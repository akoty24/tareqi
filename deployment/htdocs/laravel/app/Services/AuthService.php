<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /** @return array{user: User, token: string} */
    public function register(array $data): array
    {
        $user = User::create($data);

        event(new Registered($user));

        return ['user' => $user, 'token' => $this->issueToken($user)];
    }

    /**
     * $login may be an email address or an Egyptian mobile number.
     *
     * @return array{user: User, token: string}
     */
    public function login(string $login, string $password): array
    {
        $field = str_contains($login, '@') ? 'email' : 'phone';
        $user = User::where($field, trim($login))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw BusinessRuleException::make('invalid_credentials', 401);
        }
        if (! $user->isActive()) {
            throw BusinessRuleException::make('account_blocked', 403);
        }

        return ['user' => $user, 'token' => $this->issueToken($user)];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }
}
