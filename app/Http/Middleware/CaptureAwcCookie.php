<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Consent\CookieConsentService;
use App\Support\AwinClickCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAwcCookie
{
    public function __construct(
        private readonly CookieConsentService $consent
    ) {}

    /**
     * Capture the Awin click reference (awc) from the request query string and
     * persist it as a cookie for the configured attribution window. Runs on
     * every request so SPA navigation cannot lose the value between landing
     * and purchase.
     *
     * Consent-gated (W-0049). Affiliate tracking is not strictly necessary to
     * deliver the service, so the cookie is set only for a visitor who has
     * accepted; absence of a decision is not consent. Where consent is refused
     * — including refusal after the fact — any existing cookie is expired
     * here. It is HttpOnly, so the browser cannot do that itself, which is why
     * withdrawal has to be honoured server-side rather than in the banner.
     *
     * Consent is read from the cookie rather than the database because this
     * middleware is global: it runs before the session starts and before any
     * guard resolves a user, and it runs on every request. See
     * CookieConsentService for why the record and the cookie cannot drift.
     *
     * The cookie is NOT encrypted (added to EncryptCookies::$except) because
     * Awin needs to read the raw value at conversion time.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('awin.enabled')) {
            return $next($request);
        }

        $awc = $request->query('awc');
        $response = $next($request);

        // Read the decision AFTER the response so that the consent endpoint's
        // own request is judged on the decision it just recorded.
        if ($this->consent->resolvedStatus($request, $response) !== CookieConsentService::STATUS_ACCEPTED) {
            if ($request->cookies->has(AwinClickCookie::NAME)) {
                AwinClickCookie::forgetFrom($response);
            }

            return $response;
        }

        if (is_string($awc) && $awc !== '' && strlen($awc) <= 255) {
            AwinClickCookie::applyTo($response, $awc);
        }

        return $response;
    }
}
