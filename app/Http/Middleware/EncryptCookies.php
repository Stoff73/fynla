<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Consent\CookieConsentService;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Awin's affiliate click reference — must reach their S2S endpoint as
        // the raw value captured at click time, unencrypted by Laravel.
        'awc',
        // SP3: mobile full-site pin cookie — must be readable as plaintext for
        // the RedirectPhoneToMobile middleware pin-check.
        'm_full_site',
        // One-time web handoff marker — intentionally non-secret and consumed
        // synchronously by mScaffoldBridge before the desktop auth store loads.
        'fynla_web_session',
        // Cookie-banner consent (W-0049). Both are read by CaptureAwcCookie in
        // the GLOBAL middleware stack, which runs before this middleware — an
        // encrypted value would arrive there as ciphertext and never match a
        // decision. The status cookie is also read by the banner scripts on
        // every surface. Neither carries anything secret: the status is the
        // visitor's own choice and the subject token is an opaque random id.
        CookieConsentService::STATUS_COOKIE,
        CookieConsentService::SUBJECT_COOKIE,
    ];
}
