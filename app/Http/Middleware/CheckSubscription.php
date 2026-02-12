<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
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

        // User has active subscription or is trialing — allow through
        if ($user->hasActivePlan() || $user->onTrial()) {
            return $next($request);
        }

        return response()->json([
            'error' => 'subscription_required',
            'message' => 'Your trial has expired. Please upgrade to continue.',
        ], 403);
    }
}
