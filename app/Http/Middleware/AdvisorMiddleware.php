<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdvisorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_advisor) {
            return response()->json([
                'success' => false,
                'message' => 'Advisor access required.',
            ], 403);
        }

        return $next($request);
    }
}
