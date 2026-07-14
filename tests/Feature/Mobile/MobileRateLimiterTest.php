<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

describe('Mobile Rate Limiters', function () {
    it('registers mobile-dashboard rate limiter', function () {
        $user = User::factory()->create();

        // Verify the rate limiter is registered and callable
        expect(RateLimiter::limiter('mobile-dashboard'))->not->toBeNull();
    });

    it('registers ai-chat rate limiter', function () {
        expect(RateLimiter::limiter('ai-chat'))->not->toBeNull();
    });

    it('keeps support requests separate from the model-message allowance', function () {
        $route = Route::getRoutes()->match(Request::create(
            '/api/ai-chat/conversations/123/messages',
            'POST'
        ));

        expect($route->gatherMiddleware())
            ->toContain('throttle:60,1')
            ->toContain('throttle:ai-chat');
    });

    it('registers device-registration rate limiter', function () {
        expect(RateLimiter::limiter('device-registration'))->not->toBeNull();
    });
});
