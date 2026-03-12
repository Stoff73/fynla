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

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware(['api', 'identify.mobile'])
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
