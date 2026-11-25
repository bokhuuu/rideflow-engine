<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects internal routes that are only meant to be called by the Python TCP server.
 *
 * Checks for X-Internal-Secret header on every request.
 */
class InternalSecretMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('scooter.internal_secret');

        $providedSecret = $request->header('X-Internal-Secret');

        if (empty($expectedSecret)) {
            return $next($request);
        }

        if ($providedSecret !== $expectedSecret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
