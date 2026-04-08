<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\VerificationCode;
use App\Models\AuditLog;
use App\Models\EmailVerificationCode;
use App\Models\LoginAttempt;
use App\Models\PendingRegistration;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit\AuditService;
use App\Services\Auth\LoginLockoutService;
use App\Services\Auth\MFAService;
use App\Services\Auth\SessionService;
use App\Services\Payment\TrialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LoginLockoutService $lockoutService,
        private readonly MFAService $mfaService,
        private readonly SessionService $sessionService,
        private readonly AuditService $auditService,
        private readonly TrialService $trialService
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
            Log::info('Registration attempted with existing email', ['email_masked' => $this->maskEmail($request->email)]);

            return response()->json([
                'success' => false,
                'message' => 'An account with this email address already exists. Please sign in or reset your password.',
                'email_exists' => true,
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
            'plan' => $request->plan ?? null,
            'billing_cycle' => $request->billing_cycle ?? null,
            'referral_code' => $request->referral_code ?? null,
        ]);

        Log::info('Pending registration created', [
            'pending_id' => $pending->id,
            'email_masked' => $this->maskEmail($pending->email),
        ]);

        // Send verification email
        try {
            Mail::to($pending->email)->send(new VerificationCode(
                (object) ['first_name' => $pending->first_name, 'email' => $pending->email],
                $pending->verification_code,
                'registration'
            ));
            Log::info('Verification email sent', ['email_masked' => $this->maskEmail($pending->email)]);
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'email_masked' => $this->maskEmail($pending->email),
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

        // Check if user exists first
        $user = User::where('email', $email)->first();

        // Auto-promote admin users on login if listed in ADMIN_EMAILS
        if ($user && ! $user->is_admin && in_array($email, config('auth.admin_emails', []), true)) {
            $adminRole = \App\Models\Role::findByName(\App\Models\Role::ROLE_ADMIN);
            if ($adminRole) {
                $user->role_id = $adminRole->id;
                $user->is_admin = true;
                $user->save();
            }
        }

        if (! $user) {
            // Record failed attempt
            $this->lockoutService->recordFailedAttempt($email, LoginAttempt::REASON_INVALID_CREDENTIALS);

            // Audit log for non-existent user (no user_id)
            try {
                $this->auditService->logAuth(AuditLog::ACTION_LOGIN_FAILED, null, [
                    'email' => $email,
                    'reason' => 'user_not_found',
                ]);
            } catch (\Exception $e) {
                // Don't let audit logging crash the login flow
                Log::warning('Failed to log auth event', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            // Record failed attempt
            $this->lockoutService->recordFailedAttempt($email, LoginAttempt::REASON_INVALID_CREDENTIALS);

            // Audit log - wrap in try-catch to prevent crashes
            try {
                $this->auditService->logAuth(AuditLog::ACTION_LOGIN_FAILED, $user, [
                    'email' => $email,
                    'reason' => 'invalid_password',
                ]);
            } catch (\Exception $e) {
                // Don't let audit logging crash the login flow
                Log::warning('Failed to log auth event', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Skip verification for preview users - return token immediately
        if ($user->is_preview_user) {
            // Record successful login
            $this->lockoutService->recordSuccessfulLogin($email);

            // Audit log
            $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
                'method' => 'preview_user',
            ]);

            $authResult = $this->createAuthTokenWithSession($user);

            return $this->buildAuthSuccessResponse($user, $authResult['token'], 'Login successful');
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
                    'mfa_token' => $mfaToken,
                    'email' => $this->maskEmail($user->email),
                ],
            ]);
        }

        // Generate verification code with challenge token for login
        $challengeToken = Str::random(64);
        $verificationCode = EmailVerificationCode::generate($user->id, 'login', $challengeToken);

        try {
            Mail::to($user->email)->send(new VerificationCode($user, $verificationCode->code, 'login'));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Please check your email for verification code.',
            'requires_verification' => true,
            'data' => [
                'challenge_token' => $challengeToken,
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

        if ($token && $token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            // Delete the session record first (if exists)
            UserSession::where('token_id', $token->id)->delete();

            // Then delete the token
            $token->delete();
        }

        // Invalidate the web session if available (handles session-based auth)
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout via beacon API (for browser/tab close).
     *
     * This endpoint accepts the token in the request body (not Authorization header)
     * because sendBeacon cannot set custom headers. No auth middleware is used.
     *
     * SECURITY: This endpoint is rate-limited and only accepts POST requests.
     * The token itself serves as authentication.
     */
    public function logoutBeacon(Request $request): JsonResponse
    {
        // Accept JSON body from sendBeacon (sent as Blob with application/json)
        $data = json_decode($request->getContent(), true);
        $tokenValue = $data['token'] ?? null;

        if (! $tokenValue) {
            return response()->json(['success' => false, 'message' => 'No token provided'], 400);
        }

        // Find and revoke the token
        $accessToken = PersonalAccessToken::findToken($tokenValue);

        if ($accessToken) {
            // Get the user for audit logging
            $user = $accessToken->tokenable;

            if ($user) {
                // Audit log
                try {
                    $this->auditService->logAuth(AuditLog::ACTION_LOGOUT, $user, [
                        'method' => 'beacon',
                    ]);
                } catch (\Exception $e) {
                    // Don't let audit logging prevent logout
                    Log::warning('Failed to log beacon logout', ['error' => $e->getMessage()]);
                }
            }

            // Delete the session first (if exists)
            UserSession::where('token_id', $accessToken->id)->delete();

            // Then delete the token
            $accessToken->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        // Load spouse and role relationships
        $relations = ['role.permissions'];
        if ($user->spouse_id) {
            $relations[] = 'spouse';
        }
        $user->load($relations);

        // Include life stage data completeness so frontend has it immediately
        $dataCompletedSteps = [];
        if ($user->life_stage) {
            $lifeStageService = app(\App\Services\LifeStage\LifeStageService::class);
            $dataCompletedSteps = $lifeStageService->getDataCompleteness($user);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($user),
                'role' => $user->role?->name ?? ($user->is_admin ? 'admin' : null),
                'permissions' => $user->role?->permissions?->pluck('name')->toArray() ?? [],
                'data_completed_steps' => $dataCompletedSteps,
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
            // For login: challenge_token preferred
            'challenge_token' => 'nullable|string',
            'pending_id' => 'required_if:type,registration|integer',
        ]);

        // Handle registration verification (new flow)
        if ($request->type === 'registration') {
            $pending = PendingRegistration::find($request->pending_id);

            if (! $pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code',
                ], 422);
            }

            if ($pending->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification code has expired. Please register again.',
                ], 422);
            }

            // Check if too many failed attempts
            if ($pending->verification_attempts >= 5) {
                $pending->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please register again.',
                ], 422);
            }

            if ($pending->verification_code !== $request->code) {
                $pending->increment('verification_attempts');

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code',
                ], 422);
            }

            // Create the user from pending registration
            $adminEmails = config('auth.admin_emails', []);
            $isAdmin = in_array($pending->email, $adminEmails);
            $role = $isAdmin
                ? Role::findByName(Role::ROLE_ADMIN)
                : Role::findByName(Role::ROLE_USER);

            $user = User::create([
                'first_name' => $pending->first_name,
                'middle_name' => $pending->middle_name,
                'surname' => $pending->surname,
                'email' => $pending->email,
                'password' => $pending->password, // Already hashed
                'role_id' => $role?->id,
                'referred_by_code' => $pending->referral_code,
            ]);

            // Sync is_admin flag (bypasses guarded)
            $user->is_admin = $isAdmin;
            $user->save();

            Log::info('User created from pending registration', [
                'user_id' => $user->id,
                'pending_id' => $pending->id,
            ]);

            // Start trial — use selected plan or default to 'standard'
            $plan = ($pending->plan && in_array($pending->plan, ['student', 'standard', 'pro']))
                ? $pending->plan
                : 'standard';
            $billingCycle = in_array($pending->billing_cycle, ['monthly', 'yearly']) ? $pending->billing_cycle : 'yearly';
            $this->trialService->startTrial($user, $plan, $billingCycle);

            // Link referral if user registered with a referral code
            if ($pending->referral_code) {
                try {
                    app(\App\Services\Payment\ReferralService::class)
                        ->applyReferralOnRegistration($user, $pending->referral_code);
                } catch (\Exception $e) {
                    Log::error('Failed to link referral on registration', [
                        'user_id' => $user->id,
                        'referral_code' => $pending->referral_code,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Audit log - new user registration
            $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
                'method' => 'registration',
            ]);

            // Delete the pending registration
            $pending->delete();

            $authResult = $this->createAuthTokenWithSession($user);

            return $this->buildAuthSuccessResponse($user, $authResult['token'], 'Registration complete');
        }

        // Handle login verification (existing flow)
        // Resolve user_id from challenge_token (preferred) or direct user_id (backwards compat)
        $userId = $this->resolveLoginUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification session',
            ], 422);
        }

        $verification = EmailVerificationCode::findValidCode(
            $userId,
            $request->code,
            $request->type
        );

        if (! $verification) {
            // Record failed attempt to enforce attempt limit
            EmailVerificationCode::recordFailedAttempt($userId, $request->type);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code',
            ], 422);
        }

        // Mark code as verified
        $verification->markAsVerified();

        // Get user and create token
        $user = User::findOrFail($userId);

        // Record successful login
        $this->lockoutService->recordSuccessfulLogin($user->email);

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $user, [
            'method' => 'email_verification',
        ]);

        $authResult = $this->createAuthTokenWithSession($user);

        return $this->buildAuthSuccessResponse($user, $authResult['token'], 'Verification successful');
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
            'challenge_token' => 'nullable|string',
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

            if ($pending->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration has expired. Please register again.',
                ], 422);
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
                Log::info('Resent verification email', ['email_masked' => $this->maskEmail($pending->email)]);
            } catch (\Exception $e) {
                Log::error('Failed to resend verification email', [
                    'email_masked' => $this->maskEmail($pending->email),
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
        // Resolve user_id from challenge_token (preferred) or direct user_id (backwards compat)
        $userId = $this->resolveLoginUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification session',
            ], 422);
        }

        $user = User::findOrFail($userId);

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
            Log::error('Failed to send verification email', [
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

    /**
     * Create auth token and session for user.
     *
     * Centralizes the token creation and session tracking logic that's
     * used across login, registration verification, and MFA verification.
     *
     * @return array{token: string, session: UserSession|null}
     */
    private function createAuthTokenWithSession(User $user): array
    {
        $token = $user->createToken('auth_token', ['mfa_verified'])->plainTextToken;

        // Create session for this token
        $accessToken = $user->tokens()->latest()->first();
        $session = null;

        if ($accessToken) {
            $session = UserSession::createForToken($user, $accessToken);
        }

        return [
            'token' => $token,
            'session' => $session,
        ];
    }

    /**
     * Build standardized authentication success response.
     *
     * @param  array  $extra  Additional data to merge into the response
     */
    private function buildAuthSuccessResponse(User $user, string $token, string $message, array $extra = []): JsonResponse
    {
        // Load spouse relationship if spouse_id exists
        if ($user->spouse_id) {
            $user->load('spouse');
        }

        $data = array_merge([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'must_change_password' => $user->must_change_password ?? false,
            'mfa_enabled' => $user->mfa_enabled ?? false,
        ], $extra);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Resolve user_id from challenge_token stored on the verification code record.
     */
    private function resolveLoginUserId(Request $request): ?int
    {
        if ($request->filled('challenge_token')) {
            $verification = EmailVerificationCode::findByChallengeToken($request->challenge_token);

            return $verification?->user_id;
        }

        return null;
    }
}
