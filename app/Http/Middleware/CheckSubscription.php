<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Routes that expired users must access regardless of subscription status (all HTTP methods).
     */
    private const ALWAYS_EXCLUDED_PATHS = [
        'api/payment/',       // Subscribe, check status, cancel — required for resubscription
        'api/auth/',          // Login, logout, register, verify, password reset
        'api/webhooks/',      // Payment webhooks
        'api/preview/',       // Preview mode switching
        'api/onboarding/',    // Onboarding steps
        'api/bug-report',     // Users should always be able to report issues
        'api/gdpr/',          // GDPR: Users retain data portability/erasure rights regardless of subscription
        'api/admin/',         // Admin users are separately gated by permission middleware
        'api/advisor/',       // Advisor users are separately gated by advisor middleware
    ];

    /**
     * Routes that expired users can read but not write to.
     * Needed so users can view their profile (including subscription management tab).
     */
    private const READ_ONLY_EXCLUDED_PATHS = [
        'api/user/',
        'api/settings/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Feature flag: when payments are disabled, let everyone through
        if (! config('app.payment_enabled', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Preview users bypass subscription checks
        if ($user->is_preview_user) {
            return $next($request);
        }

        // Eagerly load subscription to avoid multiple queries in the checks below
        if (! $user->relationLoaded('subscription')) {
            $user->load('subscription');
        }

        // Allow excluded paths (payment, auth, webhooks, etc.)
        if ($this->isExcludedPath($request)) {
            return $next($request);
        }

        // User has active subscription or is trialing — allow through
        if ($user->hasActivePlan() || $user->onTrial()) {
            return $next($request);
        }

        // Expired trial or grace period — allow read-only access so users can see
        // their data behind the plan selection modal. Writes are blocked.
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        if ($user->isInGracePeriod()) {
            return response()->json([
                'error' => 'grace_period',
                'message' => 'Your subscription has expired. You have read-only access during the grace period.',
            ], 403);
        }

        return response()->json([
            'error' => 'subscription_required',
            'message' => 'Your trial has expired. Please subscribe to continue.',
        ], 403);
    }

    private function isExcludedPath(Request $request): bool
    {
        $path = $request->path();

        // Always-excluded: all HTTP methods allowed
        foreach (self::ALWAYS_EXCLUDED_PATHS as $excluded) {
            if (str_starts_with($path, $excluded)) {
                return true;
            }
        }

        // Read-only excluded: only safe methods (GET, HEAD, OPTIONS)
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            foreach (self::READ_ONLY_EXCLUDED_PATHS as $excluded) {
                if (str_starts_with($path, $excluded)) {
                    return true;
                }
            }
        }

        return false;
    }
}
