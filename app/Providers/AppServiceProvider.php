<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admin panel entry + one ability per permission (`can:users.block`, `@can`, ...).
        Gate::define('access-admin', fn (User $user) => $user->isAdmin());
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        // Password reset emails link to the Vue app, which calls POST /api/auth/reset-password.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim(config('app.frontend_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($user->email));

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->id ?: $request->ip();

        // General API ceiling (applied to every api route by throttleApi()).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($byUserOrIp($request)));

        // Brute force protection: per login identifier + IP, and per IP overall.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('login')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('trips', fn (Request $request) => Limit::perHour(30)->by($byUserOrIp($request)));
        RateLimiter::for('trip-requests', fn (Request $request) => Limit::perHour(20)->by($byUserOrIp($request)));
        RateLimiter::for('bookings', fn (Request $request) => Limit::perMinute(10)->by($byUserOrIp($request)));
        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(10)->by($byUserOrIp($request)));
        RateLimiter::for('announcements', fn (Request $request) => Limit::perHour(20)->by($byUserOrIp($request)));
    }
}
