<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Default API rate limit
        RateLimiter::for('api', function (Request $request) {
            // Higher limit for local development
            // Production needs higher limit too - dashboard makes ~15 API calls per page load
            $limit = app()->environment('local') ? 1000 : 300;

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        // Strict rate limit for authentication endpoints
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many authentication attempts. Please try again later.',
                ], 429);
            });
        });

        // Per-endpoint limiters for the unauthenticated auth surface
        // (login, register, code/MFA verification, account restore, password reset).
        //
        // These MUST be named, not inline `throttle:N,1`. Laravel keys inline
        // throttles for unauthenticated requests by sha1("$domain|$ip") with an
        // EMPTY prefix — the route path and the limit are not part of the key — so
        // every inline-throttled auth route on one IP shares ONE counter. A
        // multi-step flow then throttles itself: e.g. an MFA password reset
        // (request -> verify-email -> verify-mfa -> reset) exhausts the budget
        // before /reset and 429s a genuine first attempt (fatal for MFA users).
        // A login attempt also poisons the same bucket for a later reset.
        //
        // Keying each named limiter by "$path|$ip" gives every endpoint its own
        // bucket, so no auth step can ever throttle another. The numeric suffix is
        // the per-minute allowance; pick the closest band per route below.
        $authThrottleResponse = fn () => response()->json([
            'success' => false,
            'message' => 'Too many attempts. Please wait a moment and try again.',
        ], 429);

        foreach ([3, 5, 10] as $perMinute) {
            RateLimiter::for("auth-{$perMinute}", function (Request $request) use ($perMinute, $authThrottleResponse) {
                return Limit::perMinute($perMinute)->by($request->path().'|'.$request->ip())->response($authThrottleResponse);
            });
        }

        // Rate limit for data export (expensive operation)
        RateLimiter::for('export', function (Request $request) {
            return Limit::perHour(3)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Export limit reached. Please try again later.',
                ], 429);
            });
        });

        // Rate limit for sensitive operations (erasure, password change)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests for this operation. Please try again later.',
                ], 429);
            });
        });

        // Rate limit for bug reports (5 per hour per user/IP)
        RateLimiter::for('bug-reports', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Bug report limit reached. Please try again in an hour.',
                ], 429);
            });
        });

        // Mobile dashboard rate limit (30 requests per minute per user)
        RateLimiter::for('mobile-dashboard', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many dashboard requests. Please try again shortly.',
                ], 429);
            });
        });

        // AI chat rate limit (20 requests per minute per user)
        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many chat requests. Please wait a moment before sending another message.',
                ], 429);
            });
        });

        // Device registration rate limit (5 requests per minute per user)
        RateLimiter::for('device-registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many device registration attempts. Please try again later.',
                ], 429);
            });
        });

        RateLimiter::for('native-session', function (Request $request) {
            $key = $request->user() === null
                ? 'ip:'.$request->ip()
                : 'user:'.$request->user()->getAuthIdentifier();

            return Limit::perMinute(10)->by($key)->response(function () {
                return response()->json([
                    'success' => false,
                    'error' => 'native_session_rate_limited',
                    'message' => 'Too many native session attempts. Please try again later.',
                ], 429);
            });
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware(['api', 'identify.mobile'])
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));

            if ($this->app->environment('e2e')) {
                Route::middleware('api')
                    ->prefix('__e2e')
                    ->group(base_path('routes/e2e.php'));
            }

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
