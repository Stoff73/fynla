<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\VerificationCode;
use App\Models\EmailVerificationCode;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit\AuditService;
use App\Services\Auth\LoginLockoutService;
use App\Services\Auth\MFAService;
use App\Services\Auth\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function __construct(
        private LoginLockoutService $lockoutService,
        private MFAService $mfaService,
        private SessionService $sessionService,
        private AuditService $auditService
    ) {}

    /**
     * Register a new user.
     *
     * Creates a pending registration (not a user) until email is verified.
     * If email already has a pending registration, it gets overwritten.
     * This allows users to cancel and start fresh.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Check if email is already registered as a verified user
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered.',
                'errors' => ['email' => ['This email is already registered.']],
            ], 422);
        }

        // Create or update pending registration (allows re-registration)
        $pending = PendingRegistration::createOrUpdate([
            'email' => $request->email,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'surname' => $request->surname,
            'password' => Hash::make($request->password),
            'registration_source' => $request->registration_source ?? null,
            'preview_persona_id' => $request->preview_persona_id ?? null,
        ]);

        \Log::info('Pending registration created', [
            'pending_id' => $pending->id,
            'email' => $pending->email,
        ]);

        // Send verification email
        try {
            Mail::to($pending->email)->send(new VerificationCode(
                (object) ['first_name' => $pending->first_name, 'email' => $pending->email],
                $pending->verification_code,
                'registration'
            ));
            \Log::info('Verification email sent', ['email' => $pending->email]);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'email' => $pending->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Please check your email for verification code.',
            'requires_verification' => true,
            'data' => [
                'pending_id' => $pending->id,
                'email' => $this->maskEmail($pending->email),
            ],
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->email;

        // Check if account is locked
        if ($this->lockoutService->isLocked($email)) {
            $lockoutInfo = $this->lockoutService->getLockoutInfo($email);

            return response()->json([
                'success' => false,
                'message' => $lockoutInfo['message'],
                'locked' => true,
                'remaining_seconds' => $lockoutInfo['remaining_seconds'],
            ], 423); // 423 Locked
        }

        // Check if IP is blocked
        if ($this->lockoutService->isIpLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts from this location. Please try again later.',
                'locked' => true,
            ], 423);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            // Record failed attempt
            $this->lockoutService->recordFailedAttempt($email, LoginAttempt::REASON_INVALID_CREDENTIALS);

            // Audit log - find user if exists for better tracking
            $user = User::where('email', $email)->first();
            $this->auditService->logAuth(AuditLog::ACTION_LOGIN_FAILED, $user, [
                'email' => $email,
                'reason' => 'invalid_credentials',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = User::where('email', $email)->firstOrFail();

        // Skip verification for preview users - return token immediately
        if ($user->is_preview_user) {
            // Record successful login
            $this->lockoutService->recordSuccessfulLogin($email);

            // Audit log
            $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
                'method' => 'preview_user',
            ]);

            // Load spouse relationship if spouse_id exists
            if ($user->spouse_id) {
                $user->load('spouse');
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // Create session for this token
            $accessToken = $user->tokens()->latest()->first();
            if ($accessToken) {
                UserSession::createForToken($user, $accessToken);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'must_change_password' => $user->must_change_password,
                    'mfa_enabled' => $user->mfa_enabled,
                ],
            ]);
        }

        // Check if user has MFA enabled
        if ($this->mfaService->hasMFAEnabled($user)) {
            // Generate secure MFA challenge token
            $mfaToken = MFAController::generateChallengeToken($user->id);

            // Don't record as successful yet - MFA verification is still required
            return response()->json([
                'success' => true,
                'message' => 'MFA verification required.',
                'requires_mfa' => true,
                'data' => [
                    'user_id' => $user->id, // Kept for backwards compatibility
                    'mfa_token' => $mfaToken, // Secure challenge token
                    'email' => $this->maskEmail($user->email),
                ],
            ]);
        }

        // Generate verification code and send email for regular users
        $verificationCode = EmailVerificationCode::generate($user->id, 'login');

        try {
            Mail::to($user->email)->send(new VerificationCode($user, $verificationCode->code, 'login'));
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Please check your email for verification code.',
            'requires_verification' => true,
            'data' => [
                'user_id' => $user->id,
                'email' => $this->maskEmail($user->email),
            ],
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_LOGOUT, $user);

        if ($token) {
            // Delete the session first (if exists)
            UserSession::where('token_id', $token->id)->delete();

            // Then delete the token
            if (method_exists($token, 'delete')) {
                $token->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        // Load spouse relationship if spouse_id exists
        if ($user->spouse_id) {
            $user->load('spouse');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
                'different:current_password',
            ],
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'new_password.different' => 'New password must be different from current password.',
        ]);

        $user = $request->user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        // Update password and reset must_change_password flag
        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false;
        $user->save();

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_PASSWORD_CHANGED, $user);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Verify email code and return auth token.
     *
     * For registration: Creates user from pending registration, then deletes pending record.
     * For login: Uses existing EmailVerificationCode system.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'type' => 'required|string|in:login,registration',
            // For login, user_id is required. For registration, pending_id or email is required.
            'user_id' => 'required_if:type,login|integer',
            'pending_id' => 'required_if:type,registration|integer',
        ]);

        // Handle registration verification (new flow)
        if ($request->type === 'registration') {
            $pending = PendingRegistration::find($request->pending_id);

            if (! $pending || $pending->verification_code !== $request->code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code',
                ], 422);
            }

            // Create the user from pending registration
            $user = User::create([
                'first_name' => $pending->first_name,
                'middle_name' => $pending->middle_name,
                'surname' => $pending->surname,
                'email' => $pending->email,
                'password' => $pending->password, // Already hashed
            ]);

            \Log::info('User created from pending registration', [
                'user_id' => $user->id,
                'pending_id' => $pending->id,
            ]);

            // Audit log - new user registration
            $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
                'method' => 'registration',
            ]);

            // Delete the pending registration
            $pending->delete();

            // Create auth token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Create session for this token
            $accessToken = $user->tokens()->latest()->first();
            if ($accessToken) {
                UserSession::createForToken($user, $accessToken);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration complete',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'must_change_password' => false,
                    'mfa_enabled' => false,
                ],
            ]);
        }

        // Handle login verification (existing flow)
        $verification = EmailVerificationCode::findValidCode(
            $request->user_id,
            $request->code,
            $request->type
        );

        if (! $verification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code',
            ], 422);
        }

        // Mark code as verified
        $verification->markAsVerified();

        // Get user and create token
        $user = User::findOrFail($request->user_id);

        // Record successful login
        $this->lockoutService->recordSuccessfulLogin($user->email);

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
            'method' => 'email_verification',
        ]);

        // Load spouse relationship if spouse_id exists
        if ($user->spouse_id) {
            $user->load('spouse');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Create session for this token
        $accessToken = $user->tokens()->latest()->first();
        if ($accessToken) {
            UserSession::createForToken($user, $accessToken);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification successful',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'must_change_password' => $user->must_change_password,
                'mfa_enabled' => $user->mfa_enabled,
            ],
        ]);
    }

    /**
     * Resend verification code.
     *
     * For registration: Uses pending registration (no user exists yet).
     * For login: Uses existing EmailVerificationCode system.
     */
    public function resendCode(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:login,registration',
            'user_id' => 'required_if:type,login|integer',
            'pending_id' => 'required_if:type,registration|integer',
        ]);

        // Handle registration resend (new flow)
        if ($request->type === 'registration') {
            $pending = PendingRegistration::find($request->pending_id);

            if (! $pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration not found. Please start over.',
                ], 404);
            }

            // Regenerate the code
            $newCode = $pending->regenerateCode();

            // Send email
            try {
                Mail::to($pending->email)->send(new VerificationCode(
                    (object) ['first_name' => $pending->first_name, 'email' => $pending->email],
                    $newCode,
                    'registration'
                ));
                \Log::info('Resent verification email', ['email' => $pending->email]);
            } catch (\Exception $e) {
                \Log::error('Failed to resend verification email', [
                    'email' => $pending->email,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification email. Please try again.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent',
                'data' => [
                    'can_resend' => true,
                ],
            ]);
        }

        // Handle login resend (existing flow)
        $user = User::findOrFail($request->user_id);

        // Get the latest code for this user and type
        $existingCode = EmailVerificationCode::getLatest($user->id, $request->type);

        if ($existingCode && ! $existingCode->canResend()) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum resend limit reached. Please refresh and try again.',
                'can_resend' => false,
            ], 429);
        }

        // Generate new code (or regenerate existing)
        if ($existingCode) {
            try {
                $verificationCode = $existingCode->regenerate();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum resend limit reached. Please refresh and try again.',
                    'can_resend' => false,
                ], 429);
            }
        } else {
            $verificationCode = EmailVerificationCode::generate($user->id, $request->type);
        }

        // Send email
        try {
            Mail::to($user->email)->send(new VerificationCode($user, $verificationCode->code, $request->type));
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent',
            'data' => [
                'resend_count' => $verificationCode->resend_count,
                'can_resend' => $verificationCode->canResend(),
                'remaining_resends' => max(0, 2 - $verificationCode->resend_count),
            ],
        ]);
    }

    /**
     * Mask email address for privacy.
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        if (strlen($name) <= 2) {
            $masked = $name[0].'***';
        } else {
            $masked = $name[0].str_repeat('*', strlen($name) - 2).substr($name, -1);
        }

        return $masked.'@'.$domain;
    }
}
