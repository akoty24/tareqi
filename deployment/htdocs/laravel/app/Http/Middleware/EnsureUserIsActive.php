<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocked users cannot perform any normal platform action. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            return ApiResponse::error(__('errors.account_blocked'), 403, null, 'account_blocked');
        }

        return $next($request);
    }
}
