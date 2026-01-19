<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\AuditService;
use App\Services\Auth\MFAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MFAController extends Controller
{
    public function __construct(
        private MFAService $mfaService,
        private AuditService $auditService
    ) {}

    /**
     * Start MFA setup - generate secret and QR code
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->mfaService->hasMFAEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => 'MFA is already enabled on this account.',
            ], 400);
        }

        $secret = $this->mfaService->generateSecret();

        // Store secret temporarily in session for verification
        session(['mfa_setup_secret' => $secret]);

        $qrCodeDataUri = $this->mfaService->getQRCodeDataUri($user, $secret);

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret, // User can manually enter this in authenticator app
                'qr_code' => $qrCodeDataUri,
            ],
        ]);
    }

    /**
     * Verify setup code and enable MFA
     */
    public function verifySetup(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $secret = session('mfa_setup_secret');

        if (! $secret) {
            return response()->json([
                'success' => false,
                'message' => 'MFA setup session expired. Please start again.',
            ], 400);
        }

        if (! $this->mfaService->verifySetupCode($secret, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code. Please try again.',
            ], 400);
        }

        // Enable MFA and get recovery codes
        $recoveryCodes = $this->mfaService->enableMFA($user, $secret);

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_MFA_ENABLED, $user);

        // Clear setup session
        session()->forget('mfa_setup_secret');

        return response()->json([
            'success' => true,
            'message' => 'MFA has been enabled successfully.',
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }

    /**
     * Generate an MFA challenge token for secure verification
     * Called by AuthController when MFA is required
     */
    public static function generateChallengeToken(int $userId): string
    {
        $token = Str::random(64);
        $cacheKey = "mfa_challenge:{$token}";

        // Store challenge for 5 minutes
        Cache::put($cacheKey, [
            'user_id' => $userId,
            'created_at' => now()->timestamp,
        ], 300);

        return $token;
    }

    /**
     * Validate and consume an MFA challenge token
     */
    private function validateChallengeToken(string $token): ?int
    {
        $cacheKey = "mfa_challenge:{$token}";
        $challenge = Cache::get($cacheKey);

        if (! $challenge) {
            return null;
        }

        // Consume the token (one-time use)
        Cache::forget($cacheKey);

        return $challenge['user_id'];
    }

    /**
     * Verify MFA code during login
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'mfa_token' => 'required|string',
            // Keep user_id for backwards compatibility but prefer mfa_token
            'user_id' => 'sometimes|integer',
        ]);

        // Prefer secure mfa_token, fall back to user_id for backwards compatibility
        $userId = null;
        if ($request->filled('mfa_token')) {
            $userId = $this->validateChallengeToken($request->mfa_token);
        } elseif ($request->filled('user_id')) {
            // Backwards compatibility - will be deprecated
            $userId = $request->user_id;
        }

        // Use consistent error message to prevent user enumeration
        $genericError = response()->json([
            'success' => false,
            'message' => 'Invalid or expired verification request.',
        ], 401);

        if (! $userId) {
            return $genericError;
        }

        $user = \App\Models\User::find($userId);

        if (! $user) {
            return $genericError;
        }

        if (! $this->mfaService->verifyCode($user, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 401);
        }

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_MFA_VERIFIED, $user);
        $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
            'method' => 'mfa',
        ]);

        // MFA verified - create token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Create session for this token
        $accessToken = $user->tokens()->latest()->first();
        if ($accessToken) {
            \App\Models\UserSession::createForToken($user, $accessToken);
        }

        return response()->json([
            'success' => true,
            'message' => 'MFA verification successful.',
            'data' => [
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mfa_enabled' => $user->mfa_enabled,
                ],
            ],
        ]);
    }

    /**
     * Use a recovery code to login
     */
    public function useRecoveryCode(Request $request): JsonResponse
    {
        $request->validate([
            'recovery_code' => 'required|string',
            'mfa_token' => 'required|string',
            // Keep user_id for backwards compatibility but prefer mfa_token
            'user_id' => 'sometimes|integer',
        ]);

        // Prefer secure mfa_token, fall back to user_id for backwards compatibility
        $userId = null;
        if ($request->filled('mfa_token')) {
            $userId = $this->validateChallengeToken($request->mfa_token);
        } elseif ($request->filled('user_id')) {
            // Backwards compatibility - will be deprecated
            $userId = $request->user_id;
        }

        // Use consistent error message to prevent user enumeration
        $genericError = response()->json([
            'success' => false,
            'message' => 'Invalid or expired verification request.',
        ], 401);

        if (! $userId) {
            return $genericError;
        }

        $user = \App\Models\User::find($userId);

        if (! $user) {
            return $genericError;
        }

        if (! $this->mfaService->verifyRecoveryCode($user, $request->recovery_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid recovery code.',
            ], 401);
        }

        // Recovery code verified - create token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Create session for this token
        $accessToken = $user->tokens()->latest()->first();
        if ($accessToken) {
            \App\Models\UserSession::createForToken($user, $accessToken);
        }

        $remainingCodes = $this->mfaService->getRemainingRecoveryCodeCount($user);

        return response()->json([
            'success' => true,
            'message' => 'Recovery code accepted.',
            'data' => [
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'remaining_recovery_codes' => $remainingCodes,
                'warning' => $remainingCodes <= 2 ? 'You have very few recovery codes left. Please regenerate them.' : null,
            ],
        ]);
    }

    /**
     * Disable MFA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
            ], 401);
        }

        $this->mfaService->disableMFA($user);

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_MFA_DISABLED, $user);

        return response()->json([
            'success' => true,
            'message' => 'MFA has been disabled.',
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
            ], 401);
        }

        if (! $this->mfaService->hasMFAEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => 'MFA is not enabled on this account.',
            ], 400);
        }

        $recoveryCodes = $this->mfaService->regenerateRecoveryCodes($user);

        return response()->json([
            'success' => true,
            'message' => 'Recovery codes have been regenerated.',
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }

    /**
     * Get MFA status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'mfa_enabled' => $this->mfaService->hasMFAEnabled($user),
                'mfa_confirmed_at' => $user->mfa_confirmed_at?->toIso8601String(),
                'remaining_recovery_codes' => $this->mfaService->getRemainingRecoveryCodeCount($user),
            ],
        ]);
    }
}
