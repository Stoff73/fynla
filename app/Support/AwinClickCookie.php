<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one home for the Awin affiliate click cookie (`awc`).
 *
 * Name, flags, domain and lifetime are declared here once. Two callers need
 * them — CaptureAwcCookie (capture on an affiliate landing) and
 * CookieConsentController (clear on withdrawal) — and a second copy of the
 * cookie attributes in either would mean a clear that silently fails to match
 * the cookie it is trying to remove.
 *
 * The cookie is deliberately NOT encrypted (see EncryptCookies::$except):
 * Awin's S2S endpoint needs the raw value captured at click time.
 */
final class AwinClickCookie
{
    public const NAME = 'awc';

    /**
     * Attach the click reference for the configured attribution window.
     */
    public static function applyTo(Response $response, string $value): void
    {
        $response->headers->setCookie(
            Cookie::create(
                name: self::NAME,
                value: $value,
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

    /**
     * Expire the click reference.
     *
     * The cookie is HttpOnly, so the browser cannot clear it on the user's
     * behalf — withdrawal of consent has to be honoured from the server.
     */
    public static function forgetFrom(Response $response): void
    {
        $response->headers->clearCookie(
            self::NAME,
            '/',
            config('awin.cookie_domain'),
            true,
            true,
            Cookie::SAMESITE_LAX
        );
    }
}
