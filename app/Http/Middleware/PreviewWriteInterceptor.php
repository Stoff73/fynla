<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to intercept write operations for preview users.
 *
 * Preview users can view all data through normal APIs, but write operations
 * (POST, PUT, PATCH, DELETE) return fake success responses without persisting
 * to the database. This allows forms to work normally while keeping preview
 * data immutable.
 *
 * The frontend receives a `preview_mode: true` flag indicating the change
 * was not persisted and should be stored in sessionStorage instead.
 */
class PreviewWriteInterceptor
{
    /**
     * HTTP methods considered as "write" operations.
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Routes that should be excluded from interception (e.g., logout, preview exit).
     */
    private const EXCLUDED_ROUTES = [
        'api/preview/exit',
        'api/preview/switch',
        'api/auth/logout',
    ];

    /**
     * Route patterns that should be excluded (calculation endpoints are read operations).
     * These POST endpoints compute and return data without modifying anything.
     */
    private const EXCLUDED_PATTERNS = [
        '/calculate',           // All calculation endpoints (personal-accounts, IHT, SDLT, etc.)
        '/calculate-',          // Hyphenated calculation endpoints (calculate-sdlt, calculate-iht)
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve user from Bearer token since this runs before auth:sanctum
        $user = $this->resolveUserFromToken($request);

        // If not authenticated or not a preview user, proceed normally
        if (! $user || ! $user->is_preview_user) {
            return $next($request);
        }

        // If this is a read operation (GET, HEAD, OPTIONS), proceed normally
        if (! in_array($request->method(), self::WRITE_METHODS)) {
            return $next($request);
        }

        // Check if this route should be excluded from interception
        $currentPath = $request->path();
        foreach (self::EXCLUDED_ROUTES as $excludedRoute) {
            if (str_starts_with($currentPath, $excludedRoute)) {
                return $next($request);
            }
        }

        // Check if this route matches an excluded pattern (e.g., calculation endpoints)
        foreach (self::EXCLUDED_PATTERNS as $pattern) {
            if (str_contains($currentPath, $pattern)) {
                return $next($request);
            }
        }

        // For write operations, return a fake success response
        return $this->fakeSuccessResponse($request);
    }

    /**
     * Resolve the user from the Bearer token.
     *
     * Since this middleware runs before auth:sanctum, we need to manually
     * resolve the user from the Authorization header.
     */
    private function resolveUserFromToken(Request $request): ?\App\Models\User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        // Sanctum tokens are in format: "id|token"
        // We need to find the token and get its owner
        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return null;
        }

        return $accessToken->tokenable;
    }

    /**
     * Generate a fake success response for preview write operations.
     *
     * This response mimics what the real endpoint would return, but includes
     * a flag indicating the data was not actually persisted.
     */
    private function fakeSuccessResponse(Request $request): JsonResponse
    {
        $method = $request->method();
        $message = match ($method) {
            'POST' => 'Preview: Record created (not saved)',
            'PUT', 'PATCH' => 'Preview: Record updated (not saved)',
            'DELETE' => 'Preview: Record deleted (not saved)',
            default => 'Preview: Operation completed (not saved)',
        };

        // Include the request data in the response so the frontend can use it
        // for client-side state updates
        $responseData = [
            'success' => true,
            'message' => $message,
            'preview_mode' => true,
            'preview_notice' => 'Changes are session-only and will be lost on refresh.',
        ];

        // For POST/PUT/PATCH, include the submitted data with a fake ID if needed
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $requestData = $request->all();

            // Generate a temporary ID for newly created records
            if ($method === 'POST' && ! isset($requestData['id'])) {
                $requestData['id'] = 'preview_'.uniqid();
            }

            $responseData['data'] = $requestData;
        }

        return response()->json($responseData);
    }
}
