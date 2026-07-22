<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenRefreshController extends Controller
{
    use SanitizedErrorResponse;

    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $user->currentAccessToken();

            // Mobile/bearer-only endpoint. Under cookie-based SPA auth
            // currentAccessToken() returns a TransientToken which has no id
            // and cannot be ->delete()'d. Fail closed rather than crash.
            if (! ($currentToken instanceof PersonalAccessToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token refresh requires Bearer authentication. Cookie-based session authentication is not supported on this endpoint.',
                ], 400);
            }

            // Revoke the current token
            $currentToken->delete();

            // Create a new short-lived token (rotation). TTL is config-driven
            // (default 12h) so a leaked /m token self-expires; /m rotates on boot.
            $ttlMinutes = (int) config('sanctum.mobile_token_ttl_minutes', 720);
            $newToken = $user->createToken('mobile-token', ['*'], now()->addMinutes($ttlMinutes));

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $newToken->plainTextToken,
                    'expires_at' => $newToken->accessToken->expires_at->toIso8601String(),
                    'token_age_days' => 0,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Refreshing auth token');
        }
    }
}
