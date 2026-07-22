<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\NativeDeviceSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveNativeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $current = $user?->currentAccessToken();
        $plainBearer = $request->bearerToken();

        if (
            $user === null
            || ! $current instanceof PersonalAccessToken
            || ! is_string($plainBearer)
            || $plainBearer === ''
            || $current->abilities !== ['native']
        ) {
            return $this->invalidSessionResponse();
        }

        $resolved = PersonalAccessToken::findToken($plainBearer);

        if (
            ! $resolved instanceof PersonalAccessToken
            || (string) $resolved->getKey() !== (string) $current->getKey()
            || $resolved->tokenable_type !== $user->getMorphClass()
            || (string) $resolved->tokenable_id !== (string) $user->getKey()
        ) {
            return $this->invalidSessionResponse();
        }

        $session = NativeDeviceSession::query()
            ->where('user_id', $user->getKey())
            ->where('current_access_token_id', $current->getKey())
            ->whereNull('revoked_at')
            ->where('absolute_expires_at', '>', now())
            ->first();

        if ($session === null) {
            return $this->invalidSessionResponse();
        }

        $request->attributes->set('native_device_session', $session);

        return $next($request);
    }

    private function invalidSessionResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'native_session_invalid',
            'message' => 'Native session credentials are invalid.',
        ], 401);
    }
}
