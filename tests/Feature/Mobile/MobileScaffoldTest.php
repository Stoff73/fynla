<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('serves the mobile host page with a same-origin iframe', function () {
    $res = get('/m');

    $res->assertOk();
    $res->assertSee('<iframe', false);
    // The host iframes the REAL responsive funnel entry — the homepage ('/').
    // The funnel (homepage → /savetax → /savetax/plan → register → /m/app) then
    // runs inside this same-origin iframe. Absolute + subdir-aware via url().
    $res->assertSee('src="'.url('/').'"', false);
});

it('serves the mobile app shell at /m/app and nested paths', function () {
    get('/m/app')->assertOk()->assertSee('id="m-app"', false);
    get('/m/app/login')->assertOk()->assertSee('id="m-app"', false);
});

it('allows same-origin framing on /m and /m/app only', function () {
    $m = get('/m');
    expect($m->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($m->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");

    $app = get('/m/app');
    expect($app->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($app->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");

    $appNested = get('/m/app/login');
    expect($appNested->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($appNested->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");

    // Bare /m/app/ (trailing slash, no sub-segment) is the canonical inner-router
    // base — must keep SAMEORIGIN even though it matches neither m/app nor m/app/*.
    $appTrailing = get('/m/app/');
    expect($appTrailing->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($appTrailing->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");

    // Desktop SPA / top-level homepage stays locked down (DENY).
    $home = get('/');
    expect($home->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($home->headers->get('Content-Security-Policy'))->not->toContain('frame-ancestors');
});

it('allows SAMEORIGIN framing for any document loaded inside the mobile iframe', function () {
    // A document fetched with Sec-Fetch-Dest: iframe is being loaded inside the
    // /m mobile-view iframe, so it must be SAMEORIGIN-frameable even though the
    // same URL top-level stays DENY.
    $framedHome = get('/', ['Sec-Fetch-Dest' => 'iframe']);
    expect($framedHome->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($framedHome->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
    // Vary on Sec-Fetch-Dest so a cached top-level DENY is never served in-frame.
    expect($framedHome->headers->get('Vary'))->toContain('Sec-Fetch-Dest');
});

const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
const DESKTOP_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
const ANDROID_PHONE_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

it('redirects a phone user-agent on / to /m', function () {
    get('/', ['User-Agent' => PHONE_UA])->assertRedirect('/m');
});

it('redirects an android phone user-agent on / to /m', function () {
    get('/', ['User-Agent' => ANDROID_PHONE_UA])->assertRedirect('/m');
});

it('does not redirect desktop user-agents', function () {
    get('/', ['User-Agent' => DESKTOP_UA])->assertOk();
});

it('does not redirect /m or /m/app (no loop)', function () {
    get('/m', ['User-Agent' => PHONE_UA])->assertOk();
    get('/m/app', ['User-Agent' => PHONE_UA])->assertOk();
});

it('does not redirect a phone document loaded inside the mobile iframe (no loop)', function () {
    // The /m host iframes '/'; that in-frame load must serve the homepage, not
    // 302 back to /m (which would loop the funnel out of existence).
    get('/', ['User-Agent' => PHONE_UA, 'Sec-Fetch-Dest' => 'iframe'])->assertOk();
    get('/savetax', ['User-Agent' => PHONE_UA, 'Sec-Fetch-Dest' => 'iframe'])->assertOk();
});

it('does not redirect /api on a phone UA', function () {
    get('/api/v1/health', ['User-Agent' => PHONE_UA])->assertOk()->assertJson(['success' => true]);
});

it('honours the ?full=1 desktop escape hatch and pins via cookie', function () {
    $res = get('/?full=1', ['User-Agent' => PHONE_UA]);
    $res->assertOk();
    $res->assertPlainCookie('m_full_site', '1');

    $this->withUnencryptedCookie('m_full_site', '1')
        ->get('/', ['User-Agent' => PHONE_UA])
        ->assertOk();
});
