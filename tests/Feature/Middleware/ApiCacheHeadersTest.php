<?php

declare(strict_types=1);

use App\Http\Middleware\ApiCacheHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

/**
 * Symfony's HeaderBag normalises Cache-Control directives alphabetically
 * when set via headers->set('Cache-Control', ...), so we assert on the
 * presence of each directive rather than a fixed string.
 */
function assertNoStore(\Illuminate\Testing\TestResponse|\Symfony\Component\HttpFoundation\Response $response): void
{
    $cc = $response->headers->get('Cache-Control');

    expect($cc)->not->toBeNull();
    expect($cc)->toContain('no-store');
    expect($cc)->toContain('no-cache');
    expect($cc)->toContain('private');
    expect($cc)->toContain('must-revalidate');
    expect($cc)->toContain('max-age=0');

    expect($response->headers->get('Pragma'))->toBe('no-cache');
    expect($response->headers->get('Expires'))->toBe('0');
}

it('sets no-store cache headers on /api/* responses', function () {
    $response = $this->get('/api/insights');

    assertNoStore($response);
});

it('overrides Laravel default Cache-Control', function () {
    // /api/news goes through full middleware stack; without this middleware,
    // Symfony/Laravel session would set "private, must-revalidate" only.
    $response = $this->get('/api/news');

    assertNoStore($response);
});

it('does not affect non-api responses', function () {
    // The middleware is registered only in the 'api' middleware group, so
    // anything served by the 'web' group must not get no-store —
    // fingerprinted /build/* assets need long-lived caching.
    $response = $this->get('/');

    expect($response->headers->get('Cache-Control'))
        ->not->toContain('no-store');
});

it('handles middleware unit-level invocation', function () {
    $middleware = new ApiCacheHeaders;
    $request = Request::create('/api/insights', 'GET');

    $response = $middleware->handle($request, fn () => response('{"data":[]}', 200));

    assertNoStore($response);
});
