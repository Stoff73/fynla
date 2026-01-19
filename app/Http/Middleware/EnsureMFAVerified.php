<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMFAVerified
{
    /**
     * Handle an incoming request.
     *
     * Ensures that if a user has MFA enabled, they have verified it for this session.
     * Note: This middleware is primarily for sensitive operations.
     * The main MFA verification happens during login flow.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // If user has MFA enabled but hasn't verified in this session
        // This is checked via session flag set during MFA verification
        if ($user->mfa_enabled && ! session('mfa_verified', false)) {
            // For API requests, we rely on the token being created after MFA verification
            // This middleware is an additional safeguard for session-based requests
            return response()->json([
                'success' => false,
                'message' => 'MFA verification required.',
                'requires_mfa' => true,
            ], 403);
        }

        return $next($request);
    }
}
