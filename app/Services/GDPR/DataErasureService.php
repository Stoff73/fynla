<?php

declare(strict_types=1);

namespace App\Services\GDPR;

use App\Models\AuditLog;
use App\Models\ErasureRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataErasureService
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Request data erasure for a user (right to be forgotten)
     */
    public function requestErasure(User $user, ?string $reason = null): ErasureRequest
    {
        // Check for existing pending request
        $existingRequest = ErasureRequest::where('user_id', $user->id)
            ->whereIn('status', [ErasureRequest::STATUS_PENDING, ErasureRequest::STATUS_PROCESSING])
            ->first();

        if ($existingRequest) {
            return $existingRequest;
        }

        $request = ErasureRequest::create([
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => ErasureRequest::STATUS_PENDING,
        ]);

        // Audit log
        $this->auditService->logGDPR(AuditLog::ACTION_ERASURE_REQUESTED, $user->id, [
            'request_id' => $request->id,
            'reason' => $reason,
        ]);

        return $request;
    }

    /**
     * Confirm an erasure request (start processing)
     */
    public function confirmErasure(ErasureRequest $request): void
    {
        if (! $request->isPending()) {
            throw new \RuntimeException('Can only confirm pending erasure requests.');
        }

        $request->confirm();
    }

    /**
     * Cancel an erasure request
     */
    public function cancelErasure(ErasureRequest $request): void
    {
        if ($request->isCompleted() || $request->isCancelled()) {
            throw new \RuntimeException('Cannot cancel a completed or already cancelled request.');
        }

        $request->cancel();
    }

    /**
     * Process the erasure request - actually delete the data
     */
    public function processErasure(ErasureRequest $request, ?string $processedBy = null): void
    {
        if (! $request->isProcessing()) {
            throw new \RuntimeException('Can only process confirmed erasure requests.');
        }

        $user = $request->user;
        $deletedCategories = [];

        DB::transaction(function () use ($user, &$deletedCategories) {
            // Delete financial data in order (respecting foreign keys)
            $deletedCategories = array_merge($deletedCategories, $this->deleteFinancialData($user));

            // Delete user documents and files
            $deletedCategories = array_merge($deletedCategories, $this->deleteDocuments($user));

            // Delete user exports
            $deletedCategories = array_merge($deletedCategories, $this->deleteExports($user));

            // Anonymize audit logs (keep for compliance but remove PII)
            $this->anonymizeAuditLogs($user);
            $deletedCategories[] = 'audit_logs_anonymized';

            // Finally, anonymize the user account (don't fully delete for referential integrity)
            $this->anonymizeUser($user);
            $deletedCategories[] = 'user_account';
        });

        // Complete the request
        $request->complete($deletedCategories, $processedBy);

        // Audit log (using the now-anonymized user ID)
        $this->auditService->logGDPR(AuditLog::ACTION_ERASURE_COMPLETED, $user->id, [
            'request_id' => $request->id,
            'categories_deleted' => $deletedCategories,
        ]);
    }

    /**
     * Delete all financial data for a user
     */
    private function deleteFinancialData(User $user): array
    {
        $deleted = [];

        // Delete in order of dependencies

        // Goals and contributions
        $user->goals()->forceDelete();
        $deleted[] = 'goals';

        // Protection policies
        $user->lifePolicies()->delete();
        $user->criticalIllnessPolicies()->delete();
        $user->incomeProtectionPolicies()->delete();
        $deleted[] = 'protection_policies';

        // Pensions
        $user->dcPensions()->delete();
        $user->dbPensions()->delete();
        $user->statePension()->delete();
        $deleted[] = 'pensions';

        // Investment accounts and holdings
        foreach ($user->investmentAccounts as $account) {
            $account->holdings()->delete();
        }
        $user->investmentAccounts()->delete();
        $deleted[] = 'investment_accounts';

        // Savings accounts
        $user->savingsAccounts()->delete();
        $deleted[] = 'savings_accounts';

        // Properties and mortgages (mortgages first due to FK)
        $user->mortgages()->delete();
        $user->properties()->delete();
        $deleted[] = 'properties';

        // Business interests
        $user->businessInterests()->delete();
        $deleted[] = 'business_interests';

        // Chattels
        $user->chattels()->delete();
        $deleted[] = 'chattels';

        // Family members
        $user->familyMembers()->delete();
        $deleted[] = 'family_members';

        // Consents
        $user->consents()->delete();
        $deleted[] = 'consents';

        return $deleted;
    }

    /**
     * Delete user documents
     */
    private function deleteDocuments(User $user): array
    {
        $deleted = [];

        // Delete document files from storage
        foreach ($user->documents ?? [] as $document) {
            if ($document->file_path && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }
        }

        // Delete document records
        if (method_exists($user, 'documents')) {
            $user->documents()->delete();
            $deleted[] = 'documents';
        }

        return $deleted;
    }

    /**
     * Delete user exports
     */
    private function deleteExports(User $user): array
    {
        $exports = $user->dataExports ?? collect();

        foreach ($exports as $export) {
            if ($export->file_path && Storage::exists($export->file_path)) {
                Storage::delete($export->file_path);
            }
        }

        if (method_exists($user, 'dataExports')) {
            $user->dataExports()->delete();
        }

        return ['data_exports'];
    }

    /**
     * Anonymize audit logs - keep structure but remove PII
     */
    private function anonymizeAuditLogs(User $user): void
    {
        AuditLog::where('user_id', $user->id)->update([
            'ip_address' => null,
            'user_agent' => null,
            'metadata' => null,
        ]);
    }

    /**
     * Anonymize the user account
     */
    private function anonymizeUser(User $user): void
    {
        $anonymizedEmail = 'deleted_' . $user->id . '@anonymized.local';

        $user->update([
            'email' => $anonymizedEmail,
            'first_name' => 'Deleted',
            'middle_name' => null,
            'surname' => 'User',
            'date_of_birth' => null,
            'phone' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'county' => null,
            'postcode' => null,
            'national_insurance_number' => null,
            'employment_status' => null,
            'salary' => null,
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled' => false,
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete all sessions
        $user->sessions()->delete();
    }

    /**
     * Get pending erasure requests for admin review
     */
    public function getPendingRequests(): \Illuminate\Database\Eloquent\Collection
    {
        return ErasureRequest::pending()
            ->with('user:id,email,first_name,surname')
            ->orderBy('requested_at')
            ->get();
    }
}
