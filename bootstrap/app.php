<?php

use App\Helpers\ApiResponse;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\OptionalSanctumAuth;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [SetLocale::class]);
        $middleware->throttleApi();
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'auth.optional' => OptionalSanctumAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Every API error uses the same envelope: {success:false, message, errors?, error_code?}.
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error(__('errors.validation_failed'), 422, $e->errors(), 'validation_failed');
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error(__('errors.unauthenticated'), 401, null, 'unauthenticated');
            }
        });

        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                $message = $e->getMessage();
                $generic = $message === '' || $message === 'This action is unauthorized.';

                return ApiResponse::error($generic ? __('errors.forbidden') : $message, 403, null, 'forbidden');
            }
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error(__('errors.not_found'), 404, null, 'not_found');
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error(__('errors.too_many_requests'), 429, null, 'too_many_requests')
                    ->withHeaders($e->getHeaders());
            }
        });

        // Anything else: never leak internals unless APP_DEBUG is on.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request) || config('app.debug') || $e instanceof HttpResponseException) {
                return null;
            }
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

            return ApiResponse::error(__('errors.server_error'), $status, null, 'server_error');
        });
    })->create();
