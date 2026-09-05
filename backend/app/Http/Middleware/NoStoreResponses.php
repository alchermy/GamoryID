<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API responses carry no cache directives by default, so browsers apply
 * heuristic freshness and an edge proxy (Cloudflare) may cache them — which
 * showed up as freshly-created inventory needing several reloads to appear.
 * Force every API response to be uncacheable, unless a handler has
 * deliberately opted into caching (an explicit max-age, e.g. a file download).
 */
class NoStoreResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $cacheControl = (string) $response->headers->get('Cache-Control');

        if (! str_contains($cacheControl, 'max-age') && ! str_contains($cacheControl, 'no-store')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
