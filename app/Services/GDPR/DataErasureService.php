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
        private readonly AuditService $auditService
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
     * This performs a HARD DELETE of all user data and the user account
     */
    public function processErasure(ErasureRequest $request, ?string $processedBy = null): void
    {
        if (! $request->isProcessing()) {
            throw new \RuntimeException('Can only process confirmed erasure requests.');
        }

        $user = $request->user;
        $userId = $user->id;
        $deletedCategories = [];

        DB::transaction(function () use ($user, $userId, &$deletedCategories, $request) {
            // Delete financial data in order (respecting foreign keys)
            $deletedCategories = array_merge($deletedCategories, $this->deleteFinancialData($user));

            // Delete user documents and files
            $deletedCategories = array_merge($deletedCategories, $this->deleteDocuments($user));

            // Delete user exports
            $deletedCategories = array_merge($deletedCategories, $this->deleteExports($user));

            // Delete audit logs for this user (hard delete, not anonymize)
            AuditLog::where('user_id', $userId)->delete();
            $deletedCategories[] = 'audit_logs';

            // Delete the erasure request record before deleting user (FK constraint)
            $request->forceDelete();
            $deletedCategories[] = 'erasure_request';

            // Finally, hard delete the user account
            $this->deleteUser($user);
            $deletedCategories[] = 'user_account';
        });

        // Note: Cannot complete the request or log audit as user is deleted
        // The transaction ensures all-or-nothing deletion
    }

    /**
     * Delete user's financial data but keep account active
     * User stays logged in with empty account
     */
    public function deleteDataOnly(User $user): array
    {
        $deletedCategories = [];

        DB::transaction(function () use ($user, &$deletedCategories) {
            // Delete financial data (same as full erasure)
            $deletedCategories = $this->deleteFinancialData($user);

            // Reset profile fields that aren't needed for account
            $user->update([
                'employment_status' => null,
                'salary' => null,
                'national_insurance_number' => null,
            ]);

            $deletedCategories[] = 'profile_financial_fields';
        });

        // Log the action
        $this->auditService->logGDPR(AuditLog::ACTION_ERASURE_COMPLETED, $user->id, [
            'type' => 'data_only',
            'categories_deleted' => $deletedCategories,
        ]);

        return $deletedCategories;
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
        $user->lifeInsurancePolicies()->delete();
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
     * Hard delete the user account and all associated data
     *
     * Spouse relationship handling:
     * - If this user has a linked spouse, clear the spouse's spouse_id
     * - Delete the spouse's family_member record that represents this user
     * - The spouse account remains intact and unaffected
     * - The spouse will no longer see this user in their Family tab
     */
    private function deleteUser(User $user): void
    {
        // Clear spouse relationship before deleting
        // This ensures the linked spouse doesn't have an orphaned reference
        if ($user->spouse_id) {
            $spouse = User::find($user->spouse_id);
            if ($spouse) {
                // Delete the spouse's family_member record that represents this user
                // (the record with relationship='spouse' owned by the spouse)
                \App\Models\FamilyMember::where('user_id', $spouse->id)
                    ->where('relationship', 'spouse')
                    ->delete();

                $spouse->update(['spouse_id' => null]);
            }
        }

        // Also check if any other user has this user as their spouse
        // (handles both directions of the relationship)
        $usersWithThisSpouse = User::where('spouse_id', $user->id)->get();
        foreach ($usersWithThisSpouse as $otherUser) {
            // Delete their family_member record that represents this user
            \App\Models\FamilyMember::where('user_id', $otherUser->id)
                ->where('relationship', 'spouse')
                ->delete();

            $otherUser->update(['spouse_id' => null]);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete all sessions
        $user->sessions()->delete();

        // Hard delete the user record
        // Foreign keys with cascadeOnDelete will handle:
        // - goals, goal_contributions
        // - user_sessions
        // - user_consents
        // - data_exports
        // - password_reset_sessions
        $user->forceDelete();
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
