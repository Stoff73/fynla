<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Models\User;
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
     * Route prefixes whose endpoints require a full capability grant.
     * Longer document paths must remain before their shared prefix.
     */
    private const CAPABILITY_ROUTE_MAP = [
        'api/plans/adviser-export-pack' => 'advisor_export',
        'api/documents/upload-only' => 'document_upload',
        'api/documents/upload' => 'statement_upload',
        'api/investment/fees' => 'investment_cost_analysis',
        'api/what-if-scenarios' => 'what_if',
        'api/holistic' => 'holistic_plan',
        'api/household' => 'joint_household_view',
        'api/user/letter-to-spouse' => 'letter_to_spouse',
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

        $subscription = $user->relationLoaded('subscription')
            ? $user->subscription
            : $user->subscription()->first();

        // Pure freemium: a Free user may write while no subscription exists or
        // checkout is provisional. TierResolver and DbTierGate still resolve
        // and enforce Free access until verified payment activates Premium.
        $hasWriteAccess = $subscription === null
            || in_array($subscription->status, Subscription::PROVISIONAL_STATUSES, true)
            || $subscription->isActive();

        if ($hasWriteAccess) {
            $capabilityDenial = $this->checkCapability($request, $user);
            if ($capabilityDenial !== null) {
                return $capabilityDenial;
            }

            return $next($request);
        }

        // Remaining case: a churned PAID user whose subscription is terminal
        // (expired/cancelled past period). Read-only + grace, then hard block.
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
            'message' => 'Your subscription has expired. Please subscribe to continue.',
        ], 403);
    }

    /**
     * Resolve capability for a mapped route. Returns a structured 403 unless
     * the resolved tier has a full grant.
     */
    private function checkCapability(Request $request, User $user): ?Response
    {
        $path = $request->path();

        foreach (self::CAPABILITY_ROUTE_MAP as $routePrefix => $entityKey) {
            if (! str_starts_with($path, $routePrefix)) {
                continue;
            }

            $tier = $this->tierResolver->resolve($user);
            $capability = $this->tierStore->capabilityFor($tier, $entityKey);

            if ($capability !== 'full') {
                $targetTier = $this->tierStore->lowestTierWithCapability($entityKey, 'full');

                return response()->json([
                    'error' => 'capability_denied',
                    'capability' => $entityKey,
                    'required_tier' => $targetTier['tier'] ?? null,
                    'message' => 'Your plan does not include this feature.',
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
