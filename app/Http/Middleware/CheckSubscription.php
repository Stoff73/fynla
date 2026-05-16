<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;
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

    /**
     * Route-prefix → capability-key mapping for tier-gated module routes.
     * PR 7 adds estate entries here. Until then this map is empty and the
     * capability check below is a no-op.
     *
     * Shape: [ 'api/estate/' => 'estate', ... ]
     */
    private const CAPABILITY_ROUTE_MAP = [
        // PR 7: 'api/estate/' => 'estate',
    ];

    public function __construct(
        private readonly TierConfigurationStore $tierStore,
        private readonly TierResolver $tierResolver,
    ) {}

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

        // Tier capability check — consults the store for routes in CAPABILITY_ROUTE_MAP.
        // 'none' = denied regardless of subscription status.
        // 'teaser' / 'limited' / 'full' = allow through (gating is handled at the
        // feature level; CheckSubscription only enforces hard 'none' denials here).
        // PR 7 populates CAPABILITY_ROUTE_MAP with estate entries.
        $capabilityDenial = $this->checkCapability($request, $user);
        if ($capabilityDenial !== null) {
            return $capabilityDenial;
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

    /**
     * Resolve capability for a tier-mapped route. Returns a 403 response when
     * the user's tier has 'none' capability for the requested module, or null
     * when the route is not in CAPABILITY_ROUTE_MAP or access is allowed.
     *
     * Called by PR 7 once the estate entry is added to CAPABILITY_ROUTE_MAP.
     */
    private function checkCapability(Request $request, mixed $user): ?Response
    {
        if (empty(self::CAPABILITY_ROUTE_MAP)) {
            return null;
        }

        $path = $request->path();

        foreach (self::CAPABILITY_ROUTE_MAP as $routePrefix => $entityKey) {
            if (! str_starts_with($path, $routePrefix)) {
                continue;
            }

            $tier = $this->tierResolver->resolve($user);
            $capability = $this->tierStore->capabilityFor($tier, $entityKey);

            if ($capability === 'none') {
                return response()->json([
                    'error' => 'capability_denied',
                    'message' => 'Your plan does not include access to this module.',
                ], 403);
            }

            return null;
        }

        return null;
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
