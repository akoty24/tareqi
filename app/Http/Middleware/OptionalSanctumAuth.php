<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public routes that behave better for a signed-in user (hide their own
 * trips, show their booking): resolve the Sanctum user when a token is sent,
 * otherwise continue as a guest ($request->user() === null).
 */
class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
