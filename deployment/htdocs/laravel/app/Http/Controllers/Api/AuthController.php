<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->auth->register($request->validated());

        return $this->created([
            'user' => new UserResource($user->refresh()),
            'token' => $token,
        ], __('messages.registered'));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->auth->login($request->input('login'), $request->input('password'));

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], __('messages.logged_in'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->success(null, __('messages.logged_out'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = User::withCompletedTripCounts()->findOrFail($request->user()->id);

        return $this->success([
            'user' => new UserResource($user),
            'unread_notifications' => $user->unreadNotifications()->count(),
        ], __('messages.ok'));
    }

    /** Always returns the same response so emails cannot be enumerated. */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return $this->success(null, __('messages.reset_link_sent'));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? $this->success(null, __('messages.password_reset'))
            : $this->error(__($status), 422, ['email' => [__($status)]], 'password_reset_failed');
    }

    public function sendVerification(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return $this->success(null, __('messages.verification_sent'));
    }

    /** Link from the verification email (signed URL); redirects to the SPA. */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->away(rtrim(config('app.frontend_url'), '/').'/profile?verified=1');
    }
}
