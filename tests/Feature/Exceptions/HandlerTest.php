<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
 * Regression test for the case where `RateLimiter::for(...)->response(callback)`
 * throws `HttpResponseException` from middleware. Without the Handler branch
 * that returns `$e->getResponse()` for JsonResponse payloads, the global API
 * renderer's fallback turned every named-limiter 429 into a 500.
 *
 * Note: a route closure that itself throws HttpResponseException is caught
 * inside `Illuminate\Routing\Route::run()` and bypasses the exception
 * handler entirely — so this test exercises the middleware path, which is
 * the case the fix actually addresses.
 */

beforeEach(function () {
    RateLimiter::for('handler-test', function () {
        return Limit::perMinute(1)
            ->by('handler-test')
            ->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Slow down',
                ], 429);
            });
    });

    Route::middleware(['api', ThrottleRequests::class.':handler-test'])
        ->post('/api/__test/throttled', fn () => response()->json(['ok' => true]));
});

it('returns the throttle 429 response (HttpResponseException from middleware) instead of a generic 500', function () {
    $this->postJson('/api/__test/throttled')->assertOk();

    $this->postJson('/api/__test/throttled')
        ->assertStatus(429)
        ->assertJson([
            'success' => false,
            'message' => 'Slow down',
        ]);
});
