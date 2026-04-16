<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CaptureAwcCookie
{
    /**
     * Capture the Awin click reference (awc) from the request query string and
     * persist it as a cookie for the configured attribution window. Runs on
     * every request so SPA navigation cannot lose the value between landing
     * and purchase.
     *
     * The cookie is NOT encrypted (added to EncryptCookies::$except) because
     * Awin needs to read the raw value at conversion time. It is flagged
     * HttpOnly + Secure + SameSite=Lax and scoped to the configured domain.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('awin.enabled')) {
            return $next($request);
        }

        $awc = $request->query('awc');
        $response = $next($request);

        if (is_string($awc) && $awc !== '' && strlen($awc) <= 255) {
            $response->headers->setCookie(
                Cookie::create(
                    name: 'awc',
                    value: $awc,
                    expire: time() + (86400 * (int) config('awin.cookie_lifetime_days', 365)),
                    path: '/',
                    domain: config('awin.cookie_domain'),
                    secure: true,
                    httpOnly: true,
                    raw: false,
                    sameSite: Cookie::SAMESITE_LAX,
                )
            );
        }

        return $response;
    }
}
