<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    /**
     * Plan tier hierarchy — higher index = more access.
     */
    private const PLAN_ORDER = ['student', 'standard', 'family', 'pro'];

    /**
     * Check if the authenticated user's plan meets the required tier.
     *
     * Usage in routes: ->middleware('feature:standard')
     */
    public function handle(Request $request, Closure $next, string $requiredPlan): Response
    {
        // Feature flag: when payments are disabled, let everyone through
        if (! config('app.payment_enabled', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Preview users bypass feature gates
        if ($user->is_preview_user) {
            return $next($request);
        }

        // Eagerly load subscription to avoid N+1
        if (! $user->relationLoaded('subscription')) {
            $user->load('subscription');
        }

        // Trial users get full access to all features
        if ($user->onTrial()) {
            return $next($request);
        }

        // Determine user's tier position
        $userPlan = $user->subscription?->plan ?? 'student';
        $userTier = array_search($userPlan, self::PLAN_ORDER, true);
        $requiredTier = array_search($requiredPlan, self::PLAN_ORDER, true);

        // If either plan is unknown, allow through (defensive)
        if ($userTier === false || $requiredTier === false) {
            return $next($request);
        }

        if ($userTier < $requiredTier) {
            return response()->json([
                'error' => 'upgrade_required',
                'message' => 'This feature requires the '.ucfirst($requiredPlan).' plan or higher.',
                'required_plan' => $requiredPlan,
            ], 403);
        }

        return $next($request);
    }
}
