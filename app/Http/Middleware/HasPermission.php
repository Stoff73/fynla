<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasPermission
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Admin users (via RBAC role or legacy is_admin flag) bypass permission checks
        if ($this->permissionService->isAdmin($user)) {
            return $next($request);
        }

        if ($this->permissionService->hasAnyPermission($user, $permissions)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Access denied. Insufficient permissions.',
        ], 403);
    }
}
