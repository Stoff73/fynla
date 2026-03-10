<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenRefreshController extends Controller
{
    use SanitizedErrorResponse;

    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $user->currentAccessToken();

            // Revoke the current token
            $currentToken->delete();

            // Create a new token with 30-day expiry
            $newToken = $user->createToken('mobile-token', ['*'], now()->addDays(30));

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
