<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('serves the mobile host page with a same-origin iframe', function () {
    $res = get('/m');

    $res->assertOk();
    $res->assertSee('<iframe', false);
    $res->assertSee('src="/m/app"', false);
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

    // Desktop SPA stays locked down.
    $home = get('/');
    expect($home->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($home->headers->get('Content-Security-Policy'))->not->toContain('frame-ancestors');
});
