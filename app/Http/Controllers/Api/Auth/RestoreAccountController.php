<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Account\AccountDeletionService;
use App\Services\Audit\AuditService;
use App\Services\Auth\LoginLockoutService;
use App\Services\Auth\MFAService;
use App\Services\Auth\RegistrationHandoffService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RestoreAccountController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $service,
        private readonly RegistrationHandoffService $registrationHandoff,
        private readonly LoginLockoutService $lockoutService,
        private readonly MFAService $mfaService,
        private readonly AuditService $auditService,
    ) {}

    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required_without:registration_handoff|nullable|email',
            'registration_handoff' => 'required_without:email|nullable|string|max:4096',
            'password' => 'required|string',
        ]);

        $source = null;
        if ($request->filled('registration_handoff')) {
            try {
                $context = $this->registrationHandoff->restorationContext($request->string('registration_handoff')->toString());
                $user = $context['user'];
                $source = $context['source'];
            } catch (\InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        } else {
            $email = mb_strtolower(trim($request->string('email')->toString()));
            $user = User::withTrashed()->where('email', $email)->first();
        }

        $email = isset($user) ? mb_strtolower($user->email) : ($email ?? '');
        if ($email !== '' && $this->lockoutService->isLocked($email)) {
            $lockoutInfo = $this->lockoutService->getLockoutInfo($email);

            return response()->json([
                'message' => $lockoutInfo['message'],
                'locked' => true,
                'remaining_seconds' => $lockoutInfo['remaining_seconds'],
            ], 423);
        }
        if ($this->lockoutService->isIpLocked()) {
            return response()->json([
                'message' => 'Too many failed attempts from this location. Please try again later.',
                'locked' => true,
            ], 423);
        }

        if (! $user || ! $user->trashed()) {
            if ($email !== '') {
                $this->lockoutService->recordFailedAttempt($email, LoginAttempt::REASON_INVALID_CREDENTIALS);
            }

            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if (! Hash::check($request->input('password'), $user->password)) {
            $this->lockoutService->recordFailedAttempt($email, LoginAttempt::REASON_INVALID_CREDENTIALS);
            $this->auditService->logAuth(AuditLog::ACTION_LOGIN_FAILED, $user, [
                'method' => 'account_restoration',
                'reason' => 'invalid_password',
            ]);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if ($user->deletion_reason === 'legacy_purged') {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'restoration_token' => $this->issueToken($user, $source),
            'requires_mfa' => $this->mfaService->hasMFAEnabled($user),
        ]);
    }

    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'restoration_token' => 'required|string|max:256',
            'mfa_code' => 'nullable|string|max:128',
        ]);

        $restorationToken = $request->string('restoration_token')->toString();
        $initial = Cache::get('restoration_token:'.$restorationToken);
        if (! is_array($initial) || ! isset($initial['user_id'])) {
            return response()->json(['message' => 'Invalid or expired restoration token'], 401);
        }
        $lock = Cache::lock('restoration-account-lock:'.(int) $initial['user_id'], 10);

        try {
            return $lock->block(3, function () use ($request, $restorationToken): JsonResponse {
                $cacheKey = 'restoration_token:'.$restorationToken;
                $cached = Cache::get($cacheKey);
                if (! $cached) {
                    return response()->json(['message' => 'Invalid or expired restoration token'], 401);
                }

                $user = User::withTrashed()->find($cached['user_id']);
                if (! $user || ! $user->canBeRestored()) {
                    return response()->json(['message' => 'Account cannot be restored'], 401);
                }

                if ($this->lockoutService->isLocked($user->email)) {
                    $lockoutInfo = $this->lockoutService->getLockoutInfo($user->email);

                    return response()->json([
                        'message' => $lockoutInfo['message'],
                        'locked' => true,
                        'remaining_seconds' => $lockoutInfo['remaining_seconds'],
                    ], 423);
                }

                $requiresMfa = $this->mfaService->hasMFAEnabled($user);
                if ($requiresMfa) {
                    $mfaCode = trim($request->string('mfa_code')->toString());
                    if ($mfaCode === '') {
                        return response()->json([
                            'message' => 'Authenticator or recovery code required.',
                            'requires_mfa' => true,
                        ], 422);
                    }

                    $verified = preg_match('/^\d{6}$/', $mfaCode) === 1
                        ? $this->mfaService->verifyCode($user, $mfaCode)
                        : $this->mfaService->verifyRecoveryCode($user, $mfaCode);
                    if (! $verified) {
                        $this->lockoutService->recordFailedAttempt($user->email, LoginAttempt::REASON_MFA_FAILED);
                        $this->auditService->logAuth(AuditLog::ACTION_LOGIN_FAILED, $user, [
                            'method' => 'account_restoration',
                            'reason' => 'invalid_mfa',
                        ]);

                        return response()->json(['message' => 'Invalid authenticator or recovery code.'], 401);
                    }
                }

                Cache::forget($cacheKey);
                $this->service->restoreAccount($user);
                $user->refresh();
                $user->tokens()->delete();

                $token = $user->createToken('auth_token', ['mfa_verified'])->plainTextToken;
                $accessToken = $user->tokens()->latest()->first();
                if ($accessToken) {
                    UserSession::createForToken($user, $accessToken);
                }

                if ($requiresMfa) {
                    $this->auditService->logAuth(AuditLog::ACTION_MFA_VERIFIED, $user, [
                        'method' => 'account_restoration',
                    ]);
                }
                $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
                    'method' => 'account_restoration',
                ]);
                $this->lockoutService->recordSuccessfulLogin($user->email);

                return response()->json([
                    'token' => $token,
                    'user' => $user->only(['id', 'email', 'first_name', 'surname']),
                    'redirect_to' => ($cached['source'] ?? null) === 'savetax'
                        ? '/dashboard?openFyn=journey&from=savetax'
                        : (($cached['source'] ?? null) === 'pensioncheck'
                            ? '/dashboard?openFyn=journey&from=pensioncheck'
                            : '/dashboard?openPricing=1'),
                ]);
            });
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'This restoration request is already being processed.'], 409);
        }
    }

    private function issueToken(User $user, ?string $source = null): string
    {
        $token = Str::random(64);
        Cache::put(
            "restoration_token:{$token}",
            [
                'user_id' => $user->id,
                'source' => $source,
                'issued_at' => now()->toIso8601String(),
            ],
            now()->addMinutes(5)
        );

        return $token;
    }
}
