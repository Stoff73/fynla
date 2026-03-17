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

        $tokenId = $user->currentAccessToken()?->id;
        if (! $tokenId) {
            return $next($request);
        }

        $cached = Cache::get("advisor_impersonation:{$tokenId}");
        if ($cached) {
            $client = User::find($cached['client_id']);
            if ($client) {
                $request->attributes->set('advisor', $user);
                auth()->setUser($client);
            }
        }

        return $next($request);
    }
}
