<?php

declare(strict_types=1);

use App\Services\Consent\CookieConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

/**
 * W-0049 acceptance 1 and 2: the Awin click cookie is set only where the
 * visitor has accepted tracking, and a refusal expires one that already
 * exists. The cookie is HttpOnly, so the browser cannot clear it — withdrawal
 * has to be honoured here.
 */
beforeEach(function () {
    config()->set('awin.enabled', true);
    config()->set('awin.cookie_domain', null);
});

/**
 * The affiliate cookie the response sets, or null. Cleared cookies are
 * returned by Symfony with an expiry in the past, so the caller distinguishes
 * "set" from "expired" by the value.
 */
function awcCookieOn($response): ?Cookie
{
    foreach ($response->baseResponse->headers->getCookies() as $cookie) {
        if ($cookie->getName() === 'awc') {
            return $cookie;
        }
    }

    return null;
}

it('does not capture the click reference from an unasked visitor', function () {
    $response = $this->getJson('/api/pricing-config?awc=click-ref-xyz');

    // No decision is not consent.
    expect(awcCookieOn($response))->toBeNull();
});

it('does not capture the click reference from a visitor who declined', function () {
    $response = $this->withCredentials()->withUnencryptedCookie(
        CookieConsentService::STATUS_COOKIE,
        CookieConsentService::STATUS_DECLINED
    )->getJson('/api/pricing-config?awc=click-ref-xyz');

    expect(awcCookieOn($response))->toBeNull();
});

it('captures the click reference from a visitor who accepted', function () {
    $response = $this->withCredentials()->withUnencryptedCookie(
        CookieConsentService::STATUS_COOKIE,
        CookieConsentService::STATUS_ACCEPTED
    )->getJson('/api/pricing-config?awc=click-ref-xyz');

    $cookie = awcCookieOn($response);

    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->toBe('click-ref-xyz');
    expect($cookie->isHttpOnly())->toBeTrue();
});

it('expires an existing click cookie once the visitor has declined', function () {
    $response = $this
        ->withCredentials()
        ->withUnencryptedCookie(CookieConsentService::STATUS_COOKIE, CookieConsentService::STATUS_DECLINED)
        ->withUnencryptedCookie('awc', 'click-ref-from-before')
        ->getJson('/api/pricing-config');

    $cookie = awcCookieOn($response);

    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->toBeNull();
    expect($cookie->getExpiresTime())->toBeLessThan(time());
});

it('expires an existing click cookie for a visitor who has never been asked', function () {
    $response = $this
        ->withCredentials()
        ->withUnencryptedCookie('awc', 'click-ref-from-before')
        ->getJson('/api/pricing-config');

    $cookie = awcCookieOn($response);

    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->toBeNull();
});

it('leaves the click cookie alone entirely when Awin is disabled', function () {
    config()->set('awin.enabled', false);

    $response = $this
        ->withCredentials()
        ->withUnencryptedCookie('awc', 'click-ref-from-before')
        ->getJson('/api/pricing-config?awc=another-ref');

    expect(awcCookieOn($response))->toBeNull();
});
