<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale based on the user's language preference.
 *
 * Priority:
 *   1. ?lang= query parameter (client just clicked a flag)
 *   2. Session (client switched language earlier in this session)
 *   3. Default - Georgian
 */
class LocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['ka', 'en', 'ru', 'hy'];

        // If a lang param is in the URL, save it to session and use it.
        if ($request->has('lang') && in_array($request->query('lang'), $supported)) {
            Session::put('locale', $request->query('lang'));
        }

        // Apply locale from session, or fall back to Georgian.
        App::setLocale(Session::get('locale', 'ka'));

        return $next($request);
    }
}
