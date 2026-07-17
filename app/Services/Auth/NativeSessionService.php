<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Data\Auth\NativeDeviceContext;
use App\Data\Auth\NativeSessionCredentials;
use App\Exceptions\NativeSessionException;
use App\Models\NativeDeviceSession;
use App\Models\NativeRefreshToken;
use App\Models\User;
use App\Models\UserSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

final class NativeSessionService
{
    public function exchange(
        User $user,
        PersonalAccessToken $bootstrap,
        NativeDeviceContext $device
    ): NativeSessionCredentials {
        $now = CarbonImmutable::now();

        $result = DB::transaction(function () use (
            $user,
            $bootstrap,
            $device,
            $now,
        ): NativeSessionCredentials|NativeSessionException {
            $lockedBootstrap = PersonalAccessToken::query()
                ->whereKey($bootstrap->id)
                ->lockForUpdate()
                ->first();

            if (
                $lockedBootstrap === null
                || $lockedBootstrap->tokenable_type !== $user->getMorphClass()
                || (string) $lockedBootstrap->tokenable_id !== (string) $user->getKey()
            ) {
                return new NativeSessionException('native_session_invalid');
            }

            $authenticatedAt = $lockedBootstrap->created_at === null
                ? $now
                : CarbonImmutable::instance($lockedBootstrap->created_at);
            $absoluteExpiresAt = $authenticatedAt->addDays(90);

            if ($absoluteExpiresAt->lessThanOrEqualTo($now)) {
                return new NativeSessionException('native_full_login_required');
            }

            $accessExpiresAt = $now->addMinutes(15)->min($absoluteExpiresAt);
            $refreshExpiresAt = $now->addDays(30)->min($absoluteExpiresAt);
            $session = NativeDeviceSession::query()->create([
                'user_id' => $user->id,
                'platform' => $device->platform,
                'device_label' => $device->deviceLabel,
                'app_version' => $device->appVersion,
                'app_build' => $device->appBuild,
                'authenticated_at' => $authenticatedAt,
                'absolute_expires_at' => $absoluteExpiresAt,
                'last_used_at' => $now,
            ]);

            $access = $user->createToken(
                "native-access:{$session->id}",
                ['native'],
                $accessExpiresAt,
            );
            $session->forceFill(['current_access_token_id' => $access->accessToken->id])->save();

            $plainRefreshToken = $this->generateRefreshToken();
            $this->storeRefreshToken(
                $session,
                $plainRefreshToken,
                $refreshExpiresAt,
                $now,
            );

            UserSession::query()->where('token_id', $lockedBootstrap->id)->delete();
            $lockedBootstrap->delete();

            return new NativeSessionCredentials(
                accessToken: $access->plainTextToken,
                accessExpiresAt: $accessExpiresAt,
                refreshToken: $plainRefreshToken,
                refreshExpiresAt: $refreshExpiresAt,
                absoluteExpiresAt: $absoluteExpiresAt,
                sessionId: $session->id,
            );
        });

        if ($result instanceof NativeSessionException) {
            throw $result;
        }

        return $result;
    }

    public function rotate(
        string $plainRefreshToken,
        NativeDeviceContext $device
    ): NativeSessionCredentials {
        $now = CarbonImmutable::now();
        $tokenHash = hash('sha256', $plainRefreshToken);

        $result = DB::transaction(function () use (
            $tokenHash,
            $device,
            $now,
        ): NativeSessionCredentials|NativeSessionException {
            $refreshReference = NativeRefreshToken::query()
                ->where('token_hash', $tokenHash)
                ->first(['id', 'native_device_session_id']);

            if ($refreshReference === null) {
                return new NativeSessionException('native_session_invalid');
            }

            $session = NativeDeviceSession::query()
                ->whereKey($refreshReference->native_device_session_id)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return new NativeSessionException('native_session_invalid');
            }

            $refresh = NativeRefreshToken::query()
                ->whereKey($refreshReference->id)
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($refresh === null || $session->revoked_at !== null || $refresh->revoked_at !== null) {
                return new NativeSessionException('native_session_invalid');
            }

            if ($refresh->used_at !== null) {
                $this->revokeLocked($session, 'refresh_replayed', $now);

                return new NativeSessionException('native_session_replayed');
            }

            if (
                $session->absolute_expires_at === null
                || $session->absolute_expires_at->lessThanOrEqualTo($now)
            ) {
                $this->revokeLocked($session, 'absolute_expired', $now);

                return new NativeSessionException('native_full_login_required');
            }

            if ($refresh->expires_at === null || $refresh->expires_at->lessThanOrEqualTo($now)) {
                $this->revokeLocked($session, 'refresh_expired', $now);

                return new NativeSessionException('native_session_expired');
            }

            $refresh->forceFill([
                'used_at' => $now,
                'updated_at' => $now,
            ])->save();

            $previousAccessTokenId = $session->current_access_token_id;
            if ($previousAccessTokenId !== null) {
                PersonalAccessToken::query()->whereKey($previousAccessTokenId)->delete();
            }

            $accessExpiresAt = $now->addMinutes(15)->min($session->absolute_expires_at);
            $access = $session->user->createToken(
                "native-access:{$session->id}",
                ['native'],
                $accessExpiresAt,
            );
            $refreshExpiresAt = $now->addDays(30)->min($session->absolute_expires_at);
            $nextPlainRefreshToken = $this->generateRefreshToken();
            $this->storeRefreshToken(
                $session,
                $nextPlainRefreshToken,
                $refreshExpiresAt,
                $now,
            );

            $session->forceFill([
                'platform' => $device->platform,
                'device_label' => $device->deviceLabel,
                'app_version' => $device->appVersion,
                'app_build' => $device->appBuild,
                'current_access_token_id' => $access->accessToken->id,
                'last_used_at' => $now,
                'updated_at' => $now,
            ])->save();

            return new NativeSessionCredentials(
                accessToken: $access->plainTextToken,
                accessExpiresAt: $accessExpiresAt,
                refreshToken: $nextPlainRefreshToken,
                refreshExpiresAt: $refreshExpiresAt,
                absoluteExpiresAt: CarbonImmutable::instance($session->absolute_expires_at),
                sessionId: $session->id,
            );
        });

        if ($result instanceof NativeSessionException) {
            throw $result;
        }

        return $result;
    }

    public function revoke(NativeDeviceSession $session, string $reason): void
    {
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($session, $reason, $now): void {
            $lockedSession = NativeDeviceSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->first();

            if ($lockedSession === null || $lockedSession->revoked_at !== null) {
                return;
            }

            $this->revokeLocked($lockedSession, $reason, $now);
        });
    }

    private function generateRefreshToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function storeRefreshToken(
        NativeDeviceSession $session,
        string $plainRefreshToken,
        CarbonImmutable $expiresAt,
        CarbonImmutable $now,
    ): NativeRefreshToken {
        $refresh = new NativeRefreshToken;
        $refresh->native_device_session_id = $session->id;
        $refresh->token_hash = hash('sha256', $plainRefreshToken);
        $refresh->expires_at = $expiresAt;
        $refresh->created_at = $now;
        $refresh->updated_at = $now;
        $refresh->save();

        return $refresh;
    }

    private function revokeLocked(
        NativeDeviceSession $session,
        string $reason,
        CarbonImmutable $now,
    ): void {
        NativeRefreshToken::query()
            ->where('native_device_session_id', $session->id)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);

        $currentAccessTokenId = $session->current_access_token_id;
        $session->forceFill([
            'current_access_token_id' => null,
            'revoked_at' => $now,
            'revoke_reason' => $reason,
            'updated_at' => $now,
        ])->save();

        if ($currentAccessTokenId !== null) {
            PersonalAccessToken::query()->whereKey($currentAccessTokenId)->delete();
        }

        Log::notice('Native session revoked', [
            'session_uuid' => $session->id,
            'reason' => $reason,
        ]);
    }
}
