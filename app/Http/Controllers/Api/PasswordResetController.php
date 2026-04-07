<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\PasswordResetSession;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly PasswordResetService $passwordResetService
    ) {}

    /**
     * Request password reset - initiates the process
     */
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $result = $this->passwordResetService->initiateReset($request->email);

        return response()->json($result);
    }

    /**
     * Verify email code
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
            'code' => 'required|string|size:6',
        ]);

        $session = PasswordResetSession::findByToken($request->token);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset session. Please request a new password reset.',
            ], 400);
        }

        $result = $this->passwordResetService->verifyEmailCode($session, $request->code);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Resend email verification code
     */
    public function resendCode(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $session = PasswordResetSession::findByToken($request->token);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset session. Please request a new password reset.',
            ], 400);
        }

        $result = $this->passwordResetService->resendCode($session);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Verify MFA TOTP code
     */
    public function verifyMfa(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
            'code' => 'required|string|size:6',
        ]);

        $session = PasswordResetSession::findByToken($request->token);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset session. Please request a new password reset.',
            ], 400);
        }

        $result = $this->passwordResetService->verifyMfaCode($session, $request->code);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Use MFA recovery code
     */
    public function useMfaRecovery(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
            'recovery_code' => 'required|string',
        ]);

        $session = PasswordResetSession::findByToken($request->token);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset session. Please request a new password reset.',
            ], 400);
        }

        $result = $this->passwordResetService->verifyRecoveryCode($session, $request->recovery_code);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Reset password
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
            ],
        ]);

        $session = PasswordResetSession::findByToken($request->token);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset session. Please request a new password reset.',
            ], 400);
        }

        $result = $this->passwordResetService->resetPassword($session, $request->password);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
