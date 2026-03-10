<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

describe('Mobile Rate Limiters', function () {
    it('registers mobile-dashboard rate limiter', function () {
        $user = User::factory()->create();

        // Verify the rate limiter is registered and callable
        expect(RateLimiter::limiter('mobile-dashboard'))->not->toBeNull();
    });

    it('registers ai-chat rate limiter', function () {
        expect(RateLimiter::limiter('ai-chat'))->not->toBeNull();
    });

    it('registers device-registration rate limiter', function () {
        expect(RateLimiter::limiter('device-registration'))->not->toBeNull();
    });
});
