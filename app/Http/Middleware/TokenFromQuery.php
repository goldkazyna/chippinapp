<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenFromQuery
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('TokenFromQuery middleware hit', [
            'has_bearer' => (bool) $request->bearerToken(),
            'has_query_token' => (bool) $request->query('token'),
            'query_token_value' => $request->query('token'),
            'url' => $request->fullUrl(),
        ]);

        if (!$request->bearerToken() && $request->query('token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
            Log::info('TokenFromQuery: set Authorization header from query', [
                'bearer_after' => $request->bearerToken(),
            ]);
        }

        return $next($request);
    }
}
