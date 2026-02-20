<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * Uses PermissionService::isAdmin() for unified admin determination,
     * checking both the is_admin boolean and RBAC admin role.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $this->permissionService->isAdmin($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
