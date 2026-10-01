<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
 * Shared hosting often strips the "Authorization" header before PHP sees it,
 * which logs every user out right after login (401 on /api/auth/me).
 * Recover the bearer token from wherever the server left it, including the
 * "X-Authorization" copy the frontend always sends.
 */
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_X_AUTHORIZATION']
        ?? $headers['x-authorization']
        ?? null;
    if ($token) {
        $_SERVER['HTTP_AUTHORIZATION'] = $token;
    }
}

require __DIR__ . '/laravel/vendor/autoload.php';

$app = require_once __DIR__ . '/laravel/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
