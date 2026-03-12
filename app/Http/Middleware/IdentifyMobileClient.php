<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyMobileClient
{
    /**
     * Valid mobile platform identifiers.
     *
     * @var array<int, string>
     */
    private const VALID_PLATFORMS = ['ios', 'android', 'pwa'];

    /**
     * Handle an incoming request.
     *
     * Checks for the X-Fynla-Platform header and sets request attributes
     * to identify mobile clients. Passes through without error if header
     * is missing (web requests).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $platform = $request->header('X-Fynla-Platform');

        if ($platform !== null && in_array(strtolower($platform), self::VALID_PLATFORMS, true)) {
            $request->attributes->set('is_mobile', true);
            $request->attributes->set('mobile_platform', strtolower($platform));
        } else {
            $request->attributes->set('is_mobile', false);
            $request->attributes->set('mobile_platform', null);
        }

        return $next($request);
    }
}
