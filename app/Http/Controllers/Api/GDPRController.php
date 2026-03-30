<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\DeletionVerificationCode;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\ErasureRequest;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Audit\AuditService;
use App\Services\Auth\MFAService;
use App\Services\GDPR\ConsentService;
use App\Services\GDPR\DataErasureService;
use App\Services\GDPR\DataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GDPRController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly DataExportService $exportService,
        private readonly DataErasureService $erasureService,
        private readonly ConsentService $consentService,
        private readonly MFAService $mfaService,
        private readonly AuditService $auditService
    ) {}

    /**
     * Get user's current consent status
     */
    public function getConsents(Request $request): JsonResponse
    {
        $consents = $this->consentService->getUserConsents($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'consents' => $consents,
                'needs_reconsent' => $this->consentService->getConsentTypesNeedingReconsent($request->user()),
            ],
        ]);
    }

    /**
     * Update user consents
     */
    public function updateConsents(Request $request): JsonResponse
    {
        $request->validate([
            'consents' => 'required|array',
            'consents.*' => 'boolean',
        ]);

        $validTypes = array_keys(UserConsent::CURRENT_VERSIONS);
        $consents = array_intersect_key($request->consents, array_flip($validTypes));

        $this->consentService->recordConsents($request->user(), $consents);

        return response()->json([
            'success' => true,
            'message' => 'Consents updated successfully.',
            'data' => [
                'consents' => $this->consentService->getUserConsents($request->user()),
            ],
        ]);
    }

    /**
     * Request a data export
     */
    public function requestExport(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'sometimes|string|in:json,csv',
        ]);

        $format = $request->format ?? DataExport::FORMAT_JSON;
        $export = $this->exportService->requestExport($request->user(), $format);

        // Process immediately for now (could be queued for large datasets)
        if ($export->isPending()) {
            $this->exportService->processExport($export);
            $export->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => $export->isCompleted()
                ? 'Your data export is ready for download.'
                : 'Your data export request has been received.',
            'data' => [
                'export_id' => $export->id,
                'status' => $export->status,
                'format' => $export->format,
                'expires_at' => $export->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get export status
     */
    public function getExportStatus(Request $request): JsonResponse
    {
        $export = DataExport::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->first();

        if (! $export) {
            return response()->json([
                'success' => false,
                'message' => 'No export request found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'export_id' => $export->id,
                'status' => $export->status,
                'format' => $export->format,
                'file_size' => $export->file_size,
                'requested_at' => $export->requested_at?->toIso8601String(),
                'completed_at' => $export->completed_at?->toIso8601String(),
                'expires_at' => $export->expires_at?->toIso8601String(),
                'is_downloadable' => $export->isDownloadable(),
            ],
        ]);
    }

    /**
     * Download the export file
     */
    public function downloadExport(Request $request, int $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $export = DataExport::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $export) {
            return response()->json([
                'success' => false,
                'message' => 'Export not found.',
            ], 404);
        }

        if (! $export->isDownloadable()) {
            return response()->json([
                'success' => false,
                'message' => $export->isExpired()
                    ? 'This export has expired. Please request a new one.'
                    : 'Export is not ready for download.',
            ], 400);
        }

        $filePath = $this->exportService->getExportFile($export);

        if (! $filePath || ! file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Export file not found.',
            ], 404);
        }

        $filename = 'fynla_data_export_'.now()->format('Y-m-d').'.'.$export->format;

        return response()->download($filePath, $filename);
    }

    /**
     * Request account deletion (right to be forgotten)
     */
    public function requestErasure(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
            'confirm' => 'required|boolean|accepted',
        ]);

        // Preview users cannot request erasure
        if ($request->user()->is_preview_user) {
            return response()->json([
                'success' => false,
                'message' => 'Preview accounts cannot be deleted.',
            ], 403);
        }

        $erasureRequest = $this->erasureService->requestErasure(
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Your data erasure request has been submitted. You will receive an email to confirm the deletion.',
            'data' => [
                'request_id' => $erasureRequest->id,
                'status' => $erasureRequest->status,
                'requested_at' => $erasureRequest->requested_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get erasure request status
     */
    public function getErasureStatus(Request $request): JsonResponse
    {
        $erasureRequest = ErasureRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->first();

        if (! $erasureRequest) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_request' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_request' => true,
                'request_id' => $erasureRequest->id,
                'status' => $erasureRequest->status,
                'requested_at' => $erasureRequest->requested_at?->toIso8601String(),
                'confirmed_at' => $erasureRequest->confirmed_at?->toIso8601String(),
                'completed_at' => $erasureRequest->completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Confirm erasure request
     */
    public function confirmErasure(Request $request, int $id): JsonResponse
    {
        $erasureRequest = ErasureRequest::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $erasureRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Erasure request not found.',
            ], 404);
        }

        if (! $erasureRequest->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'This request cannot be confirmed.',
            ], 400);
        }

        $this->erasureService->confirmErasure($erasureRequest);

        // Process immediately (in production, this might be queued)
        $this->erasureService->processErasure($erasureRequest);

        return response()->json([
            'success' => true,
            'message' => 'Your account and all associated data has been deleted.',
        ]);
    }

    /**
     * Cancel erasure request
     */
    public function cancelErasure(Request $request, int $id): JsonResponse
    {
        $erasureRequest = ErasureRequest::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $erasureRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Erasure request not found.',
            ], 404);
        }

        try {
            $this->erasureService->cancelErasure($erasureRequest);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Erasure request has been cancelled.',
        ]);
    }

    /**
     * Get consent history
     */
    public function getConsentHistory(Request $request): JsonResponse
    {
        $history = $this->consentService->getConsentHistory($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'history' => $history->map(fn ($c) => [
                    'type' => $c->consent_type,
                    'version' => $c->version,
                    'consented' => $c->consented,
                    'consented_at' => $c->consented_at?->toIso8601String(),
                    'withdrawn_at' => $c->withdrawn_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    // =========================================================================
    // IMMEDIATE SELF-SERVICE DELETION ENDPOINTS
    // =========================================================================

    /**
     * Step 1: Initiate deletion - check 2FA status, send email code if needed
     * POST /auth/gdpr/erasure/initiate
     */
    public function initiateErasure(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:account,data',
        ]);

        $user = $request->user();

        // Block preview users
        if ($user->is_preview_user) {
            return response()->json([
                'success' => false,
                'message' => 'Preview accounts cannot be deleted.',
            ], 403);
        }

        // Generate deletion session token (64 chars, 15 min expiry)
        $sessionToken = Str::random(64);
        $cacheKey = "deletion_session:{$user->id}";

        Cache::put($cacheKey, [
            'token' => $sessionToken,
            'type' => $request->type,
            'verified' => false,
            'attempts' => 0,
        ], now()->addMinutes(15));

        // Log initiation
        $this->auditService->logGDPR(AuditLog::ACTION_ERASURE_REQUESTED, $user->id, [
            'type' => $request->type,
            'step' => 'initiated',
        ]);

        // Check if 2FA enabled
        if ($this->mfaService->hasMFAEnabled($user)) {
            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'requires_email_verification' => false,
                'session_token' => $sessionToken,
            ]);
        }

        // Send email verification code
        $this->sendDeletionVerificationEmail($user);

        return response()->json([
            'success' => true,
            'requires_2fa' => false,
            'requires_email_verification' => true,
            'session_token' => $sessionToken,
        ]);
    }

    /**
     * Step 2: Verify identity with 2FA or email code
     * POST /auth/gdpr/erasure/verify
     */
    public function verifyErasure(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|size:64',
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $cacheKey = "deletion_session:{$user->id}";
        $session = Cache::get($cacheKey);

        // Validate session exists and matches token
        if (! $session || $session['token'] !== $request->session_token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session. Please start again.',
            ], 400);
        }

        // Check attempt limit
        if ($session['attempts'] >= 3) {
            Cache::forget($cacheKey);
            Cache::forget("deletion_code:{$user->id}");

            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please start again.',
            ], 400);
        }

        // Verify code based on verification method
        $codeValid = false;

        if ($this->mfaService->hasMFAEnabled($user)) {
            // Verify 2FA TOTP code
            $codeValid = $this->mfaService->verifyCode($user, $request->code);
        } else {
            // Verify email code
            $codeValid = $this->verifyDeletionEmailCode($user, $request->code);
        }

        if (! $codeValid) {
            // Increment attempts
            $session['attempts']++;
            Cache::put($cacheKey, $session, now()->addMinutes(15));

            $remainingAttempts = 3 - $session['attempts'];

            return response()->json([
                'success' => false,
                'message' => $remainingAttempts > 0
                    ? "Invalid verification code. {$remainingAttempts} attempt(s) remaining."
                    : 'Too many failed attempts. Please start again.',
            ], 401);
        }

        // Mark session as verified
        $session['verified'] = true;
        $session['verified_at'] = now()->timestamp;
        Cache::put($cacheKey, $session, now()->addMinutes(15));

        // Clean up email code if used
        Cache::forget("deletion_code:{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Identity verified successfully.',
            'session_token' => $request->session_token,
            'type' => $session['type'],
        ]);
    }

    /**
     * Step 3: Execute deletion with confirmation phrase
     * POST /auth/gdpr/erasure/execute
     */
    public function executeErasure(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|size:64',
            'confirmation' => 'required|string',
        ]);

        $user = $request->user();
        $cacheKey = "deletion_session:{$user->id}";
        $session = Cache::get($cacheKey);

        // Validate session exists, matches token, and is verified
        if (! $session || $session['token'] !== $request->session_token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session. Please start again.',
            ], 400);
        }

        if (! $session['verified']) {
            return response()->json([
                'success' => false,
                'message' => 'Identity not verified. Please complete verification first.',
            ], 400);
        }

        // Validate confirmation phrase (case-sensitive)
        $expectedPhrase = $session['type'] === 'account' ? 'Delete my Account' : 'Delete my Data';
        if ($request->confirmation !== $expectedPhrase) {
            return response()->json([
                'success' => false,
                'message' => "Please type exactly: \"{$expectedPhrase}\"",
            ], 400);
        }

        // Clean up session
        Cache::forget($cacheKey);

        // Execute deletion based on type
        if ($session['type'] === 'account') {
            // Full account deletion - creates erasure request and processes immediately
            $erasureRequest = $this->erasureService->requestErasure($user, 'Self-service account deletion');
            $this->erasureService->confirmErasure($erasureRequest);
            $this->erasureService->processErasure($erasureRequest, 'self-service');

            return response()->json([
                'success' => true,
                'message' => 'Your account and all associated data has been deleted.',
                'type' => 'account',
                'logout_required' => true,
            ]);
        } else {
            // Data-only deletion - keep account but clear financial data
            $deletedCategories = $this->erasureService->deleteDataOnly($user);

            $this->auditService->logGDPR(AuditLog::ACTION_ERASURE_COMPLETED, $user->id, [
                'type' => 'data_only',
                'categories_deleted' => $deletedCategories,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your financial data has been deleted. Your account remains active.',
                'type' => 'data',
                'logout_required' => false,
                'deleted_categories' => $deletedCategories,
            ]);
        }
    }

    /**
     * Resend deletion verification email code
     * POST /auth/gdpr/erasure/resend-code
     */
    public function resendDeletionCode(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|size:64',
        ]);

        $user = $request->user();
        $cacheKey = "deletion_session:{$user->id}";
        $session = Cache::get($cacheKey);

        // Validate session
        if (! $session || $session['token'] !== $request->session_token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session. Please start again.',
            ], 400);
        }

        // Only allow resend for email verification (not 2FA)
        if ($this->mfaService->hasMFAEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Use your authenticator app for verification.',
            ], 400);
        }

        // Send new code
        $this->sendDeletionVerificationEmail($user);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
        ]);
    }

    /**
     * Send deletion verification email with 6-digit code
     */
    private function sendDeletionVerificationEmail(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("deletion_code:{$user->id}", [
            'code' => Hash::make($code),
            'created_at' => now()->timestamp,
        ], now()->addMinutes(15));

        Mail::to($user)->send(new DeletionVerificationCode($user, $code));
    }

    /**
     * Verify deletion email code
     */
    private function verifyDeletionEmailCode(User $user, string $code): bool
    {
        $cacheKey = "deletion_code:{$user->id}";
        $storedCode = Cache::get($cacheKey);

        if (! $storedCode) {
            return false;
        }

        return Hash::check($code, $storedCode['code']);
    }
}
