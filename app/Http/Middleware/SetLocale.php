<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Arabic by default; `Accept-Language: en` switches API messages to English. */
class SetLocale
{
    private const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // Falls back to the first supported locale (Arabic) when nothing matches.
        app()->setLocale($request->getPreferredLanguage(self::SUPPORTED));

        return $next($request);
    }
}
