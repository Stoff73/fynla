<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AdvisorImpersonationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Don't swap user on advisor routes — advisor needs to stay as themselves
        if ($request->is('api/advisor/*') || $request->is('api/advisor')) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if (! $token || ! ($token instanceof \Laravel\Sanctum\PersonalAccessToken)) {
            return $next($request);
        }
        $tokenId = $token->id;

        $cached = Cache::get("advisor_impersonation:{$tokenId}");
        if ($cached) {
            $clientId = $cached['client_id'];

            // Validate advisor actually has this client assigned
            $isAssigned = $user->advisorClients()->where('client_id', $clientId)->exists();
            if (! $isAssigned) {
                // Invalid impersonation attempt - clear cache and continue as advisor
                Cache::forget("advisor_impersonation:{$tokenId}");

                return $next($request);
            }

            $client = User::find($clientId);
            if ($client) {
                $request->attributes->set('advisor', $user);
                auth()->setUser($client);
            }
        }

        return $next($request);
    }
}
