<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates internal agent-to-Laravel API requests via a shared token.
 *
 * The Python agent sidecar sends X-Agent-Token on every callback.
 * This middleware verifies it matches the configured secret.
 */
class AgentTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Agent-Token');
        $expectedToken = config('services.anthropic.agent_internal_token');

        if (! $token || ! $expectedToken || ! hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
