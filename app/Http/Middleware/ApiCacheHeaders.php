<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiCacheHeaders
{
    /**
     * Force no-store cache headers on all /api/* responses.
     *
     * Lives in middleware (not .htaccess) because SiteGround production vhost
     * silently drops conditional Apache directives (`<If>`, `SetEnvIf env=…`,
     * `RewriteRule [E=…]` env vars consumed by `Header set env=`). Unconditional
     * `Header always set` works there, but we cannot apply no-store globally —
     * fingerprinted /build/* assets need long-lived caching. Setting headers in
     * the api middleware group bypasses Apache entirely and runs identically on
     * every host (local, csjones, fynla.org).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, private, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
