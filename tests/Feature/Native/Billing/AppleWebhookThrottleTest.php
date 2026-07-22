<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

// Deliberately bridge-free: these assertions cover the route middleware and
// the named limiter's shape without invoking AppleNotificationController,
// whose Apple bridge needs APPLE_STORE_* configuration.

it('throttles the Apple notifications webhook with the named limiter', function (): void {
    $route = collect(Route::getRoutes())->first(
        fn ($candidate) => $candidate->getName() === 'api.webhooks.apple.v2'
    );

    expect($route)->not->toBeNull();
    // Route-level middleware only — gatherMiddleware() would instantiate the
    // controller (and its Apple bridge) to collect controller middleware.
    expect($route->middleware())->toContain('throttle:apple-webhook');
});

it('limits the apple-webhook limiter to sixty requests a minute per IP with a JSON 429', function (): void {
    $limiter = RateLimiter::limiter('apple-webhook');
    expect($limiter)->not->toBeNull();

    $request = Request::create('/api/webhooks/apple/v2', 'POST', server: [
        'REMOTE_ADDR' => '203.0.113.61',
    ]);
    $limit = $limiter($request);

    expect($limit->maxAttempts)->toBe(60);
    expect($limit->decayMinutes)->toBe(1);
    expect($limit->key)->toBe('203.0.113.61');

    $response = ($limit->responseCallback)($request, []);
    expect($response->getStatusCode())->toBe(429);
    expect($response->getData(true))->toBe([
        'success' => false,
        'message' => 'Too many requests.',
    ]);
});
