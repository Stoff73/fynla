<?php

declare(strict_types=1);

/**
 * Regression: the password-reset flow must not rate-limit itself.
 *
 * Laravel's inline `throttle:N,1` middleware keys unauthenticated requests by
 * sha1("$domain|$ip") with an EMPTY prefix — the route path and the limit `N`
 * are not part of the key. That means every inline-throttled `/auth/*` route on
 * the same IP shares ONE counter. An MFA user's reset flow makes four sequential
 * calls (request -> verify-email -> verify-mfa -> reset); by the time it reaches
 * `/reset` (limit 3) the shared counter is already at 3, so `/reset` returns 429
 * on a genuine first attempt and the user can never reset their password.
 *
 * The fix gives each step its own isolated, per-endpoint named limiter.
 */
it('does not let earlier reset steps exhaust the final /reset throttle (MFA flow)', function () {
    // Three preceding steps an MFA user hits before /reset. Each consumes a
    // throttle slot (the middleware runs before validation, so the bodies can
    // be invalid — we only care that a slot is consumed).
    $this->postJson('/api/auth/password-reset/request', ['email' => 'nobody@example.com']);
    $this->postJson('/api/auth/password-reset/verify-email', ['token' => str_repeat('a', 64), 'code' => '000000']);
    $this->postJson('/api/auth/password-reset/verify-mfa', ['token' => str_repeat('a', 64), 'code' => '000000']);

    $response = $this->postJson('/api/auth/password-reset/reset', [
        'token' => str_repeat('a', 64),
        'password' => 'NewPassw0rd!',
        'password_confirmation' => 'NewPassw0rd!',
    ]);

    // Must reach the controller (invalid token -> 400), NOT be throttled.
    expect($response->status())->not->toBe(429);
});

it('does not let prior login attempts poison the password reset throttle', function () {
    // A user who mistypes their password a few times then starts a reset must
    // not inherit those login hits — previously they shared one IP bucket.
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
    }

    $response = $this->postJson('/api/auth/password-reset/reset', [
        'token' => str_repeat('a', 64),
        'password' => 'NewPassw0rd!',
        'password_confirmation' => 'NewPassw0rd!',
    ]);

    expect($response->status())->not->toBe(429);
});

it('isolates login from register so one cannot exhaust the other', function () {
    // Burn the login bucket to its limit (5/min).
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
    }

    // Register must still be reachable (its own bucket).
    $response = $this->postJson('/api/auth/register', []);

    expect($response->status())->not->toBe(429);
});

it('isolates each reset endpoint into its own throttle bucket', function () {
    // Hammer /verify-email up to its own limit; it must not bleed into /reset.
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/auth/password-reset/verify-email', [
            'token' => str_repeat('a', 64),
            'code' => '000000',
        ]);
    }

    $response = $this->postJson('/api/auth/password-reset/reset', [
        'token' => str_repeat('a', 64),
        'password' => 'NewPassw0rd!',
        'password_confirmation' => 'NewPassw0rd!',
    ]);

    expect($response->status())->not->toBe(429);
});
