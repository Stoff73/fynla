<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\SessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchSessionActivity
{
    public function __construct(private readonly SessionService $sessionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            $this->sessionService->updateCurrentSessionActivity($user);
        }

        return $next($request);
    }
}
