<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Native\Auth;

use App\Data\Auth\NativeDeviceContext;
use App\Data\Auth\NativeSessionCredentials;
use App\Exceptions\NativeSessionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Native\Auth\NativeSessionExchangeRequest;
use App\Http\Requests\V1\Native\Auth\NativeSessionRefreshRequest;
use App\Models\NativeDeviceSession;
use App\Services\Auth\NativeSessionService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class NativeSessionController extends Controller
{
    public function __construct(
        private readonly NativeSessionService $nativeSessions,
    ) {}

    public function exchange(NativeSessionExchangeRequest $request): JsonResponse
    {
        $user = $request->user();
        $bootstrap = $this->bearerAccessToken($request);

        if (
            $user === null
            || $user->is_preview_user
            || $bootstrap === null
            || $this->hasOnlyNativeAbility($bootstrap)
        ) {
            return $this->nativeError('native_session_invalid');
        }

        try {
            $credentials = $this->nativeSessions->exchange(
                $user,
                $bootstrap,
                $this->deviceContext($request),
            );
        } catch (NativeSessionException $exception) {
            return $this->nativeError($exception->errorCode);
        }

        return $this->credentialsResponse($credentials);
    }

    public function refresh(NativeSessionRefreshRequest $request): JsonResponse
    {
        try {
            $credentials = $this->nativeSessions->rotate(
                $request->validated('refresh_token'),
                $this->deviceContext($request),
            );
        } catch (NativeSessionException $exception) {
            return $this->nativeError($exception->errorCode);
        }

        return $this->credentialsResponse($credentials);
    }

    public function show(Request $request): JsonResponse
    {
        $session = $this->currentSession($request);

        if ($session === null) {
            return $this->sessionNotFound();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'platform' => $session->platform,
                'device_label' => $session->device_label,
                'app_version' => $session->app_version,
                'app_build' => $session->app_build,
                'authenticated_at' => $this->utcDate($session->authenticated_at),
                'absolute_expires_at' => $this->utcDate($session->absolute_expires_at),
                'last_used_at' => $this->utcDate($session->last_used_at),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $session = $this->currentSession($request);

        if ($session === null) {
            return $this->sessionNotFound();
        }

        $this->nativeSessions->revoke($session, 'user_logout');

        return response()->json([
            'success' => true,
            'message' => 'Native session revoked.',
        ]);
    }

    private function bearerAccessToken(Request $request): ?PersonalAccessToken
    {
        $user = $request->user();
        $current = $user?->currentAccessToken();
        $plainBearer = $request->bearerToken();

        if (! $current instanceof PersonalAccessToken || ! is_string($plainBearer) || $plainBearer === '') {
            return null;
        }

        $resolved = PersonalAccessToken::findToken($plainBearer);

        if (
            ! $resolved instanceof PersonalAccessToken
            || (string) $resolved->getKey() !== (string) $current->getKey()
            || $resolved->tokenable_type !== $user->getMorphClass()
            || (string) $resolved->tokenable_id !== (string) $user->getKey()
        ) {
            return null;
        }

        return $resolved;
    }

    private function currentSession(Request $request): ?NativeDeviceSession
    {
        $user = $request->user();
        $token = $this->bearerAccessToken($request);

        if ($user === null || $user->is_preview_user || $token === null) {
            return null;
        }

        return NativeDeviceSession::query()
            ->where('user_id', $user->getKey())
            ->where('current_access_token_id', $token->getKey())
            ->whereNull('revoked_at')
            ->first();
    }

    private function hasOnlyNativeAbility(PersonalAccessToken $token): bool
    {
        return $token->abilities === ['native'];
    }

    private function deviceContext(
        NativeSessionExchangeRequest|NativeSessionRefreshRequest $request,
    ): NativeDeviceContext {
        return new NativeDeviceContext(
            platform: (string) $request->attributes->get('fynla_client'),
            deviceLabel: $request->validated('device_label'),
            appVersion: (string) $request->attributes->get('fynla_version'),
            appBuild: (string) $request->attributes->get('fynla_build'),
        );
    }

    private function credentialsResponse(NativeSessionCredentials $credentials): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $credentials->accessToken,
                'access_expires_at' => $this->utcDate($credentials->accessExpiresAt),
                'refresh_token' => $credentials->refreshToken,
                'refresh_expires_at' => $this->utcDate($credentials->refreshExpiresAt),
                'absolute_expires_at' => $this->utcDate($credentials->absoluteExpiresAt),
                'session_id' => $credentials->sessionId,
            ],
        ]);
    }

    private function nativeError(string $errorCode): JsonResponse
    {
        $message = match ($errorCode) {
            'native_session_expired' => 'Native session has expired.',
            'native_session_replayed' => 'Native session refresh was rejected.',
            'native_full_login_required' => 'Full login is required.',
            default => 'Native session credentials are invalid.',
        };

        $stableCode = in_array($errorCode, [
            'native_session_invalid',
            'native_session_expired',
            'native_session_replayed',
            'native_full_login_required',
        ], true) ? $errorCode : 'native_session_invalid';

        return response()->json([
            'success' => false,
            'error' => $stableCode,
            'message' => $message,
        ], 401);
    }

    private function sessionNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'native_session_not_found',
            'message' => 'Native session not found.',
        ], 404);
    }

    private function utcDate(?CarbonInterface $date): ?string
    {
        return $date?->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
