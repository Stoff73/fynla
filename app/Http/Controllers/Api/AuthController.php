<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\VerificationCode;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'surname' => $request->surname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        \Log::info('User registered', [
            'user_id' => $user->id,
        ]);

        // Generate verification code and send email
        $verificationCode = EmailVerificationCode::generate($user->id, 'registration');

        try {
            Mail::to($user->email)->send(new VerificationCode($user, $verificationCode->code, 'registration'));
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email for verification code.',
            'requires_verification' => true,
            'data' => [
                'user_id' => $user->id,
                'email' => $this->maskEmail($user->email),
            ],
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Skip verification for preview users - return token immediately
        if ($user->is_preview_user) {
            // Load spouse relationship if spouse_id exists
            if ($user->spouse_id) {
                $user->load('spouse');
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'must_change_password' => $user->must_change_password,
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
        // Check if user has a current access token before deleting
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
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

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Verify email code and return auth token.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string|size:6',
            'type' => 'required|string|in:login,registration',
        ]);

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

        // Load spouse relationship if spouse_id exists
        if ($user->spouse_id) {
            $user->load('spouse');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Verification successful',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'must_change_password' => $user->must_change_password,
            ],
        ]);
    }

    /**
     * Resend verification code.
     */
    public function resendCode(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|string|in:login,registration',
        ]);

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
