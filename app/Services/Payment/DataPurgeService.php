<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataPurgeService
{
    /**
     * Permanently delete all financial data for a user.
     *
     * Cascades through all modules (Protection, Savings, Investment,
     * Retirement, Estate, Goals, Properties, Documents) respecting
     * foreign key constraints. Joint-owned records where this user
     * is the secondary owner have their joint_owner_id nullified
     * by the database (ON DELETE SET NULL) when the user is deleted.
     *
     * @return array{tables_purged: int, records_deleted: int}
     */
    public function purgeUserData(User $user): array
    {
        $userId = $user->id;
        $userEmail = $user->email;
        $totalDeleted = 0;
        $tablesPurged = 0;

        DB::transaction(function () use ($userId, $userEmail, &$totalDeleted, &$tablesPurged) {

            // ─── Phase 1: Clean up reverse references in OTHER users' records ───
            $this->cleanupReverseReferences($userId);

            // ─── Phase 2: Delete uploaded documents from disk ───
            $this->deleteDocumentFiles($userId);

            // ─── Phase 3: Delete polymorphic holdings ───
            $count = $this->deleteHoldings($userId);
            if ($count > 0) {
                $totalDeleted += $count;
                $tablesPurged++;
            }

            // ─── Phase 4: Delete all user-owned records by module ───
            $tables = $this->getDeletionOrder();

            foreach ($tables as $table) {
                $count = DB::table($table)->where('user_id', $userId)->delete();
                if ($count > 0) {
                    $totalDeleted += $count;
                    $tablesPurged++;
                }
            }

            // ─── Phase 5: Delete records keyed by email (no FK) ───
            $count = DB::table('login_attempts')->where('email', $userEmail)->delete();
            if ($count > 0) {
                $totalDeleted += $count;
                $tablesPurged++;
            }

            // ─── Phase 6: Anonymise audit logs (GDPR: keep log structure, remove all PII) ───
            $count = DB::table('audit_logs')->where('user_id', $userId)->update([
                'user_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'old_values' => null,
                'new_values' => null,
                'metadata' => null,
            ]);
            if ($count > 0) {
                $tablesPurged++;
            }

            // ─── Phase 7: Delete Sanctum tokens (polymorphic) ───
            $count = DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->delete();
            if ($count > 0) {
                $totalDeleted += $count;
                $tablesPurged++;
            }

            // ─── Phase 8: Soft-delete the user and scrub all PII ───
            // Email is preserved so the user can re-register with the same address.
            // All other personal, financial, and authentication data is nullified.
            User::withoutGlobalScopes()->where('id', $userId)->update([
                'deleted_at' => now(),
                // Identity
                'first_name' => null,
                'surname' => null,
                'middle_name' => null,
                'phone' => null,
                'date_of_birth' => null,
                'gender' => null,
                'national_insurance_number' => null,
                // Address
                'address_line_1' => null,
                'address_line_2' => null,
                'city' => null,
                'county' => null,
                'postcode' => null,
                // Employment
                'occupation' => null,
                'employer' => null,
                'industry' => null,
                // Income
                'annual_employment_income' => null,
                'annual_self_employment_income' => null,
                'annual_rental_income' => null,
                'annual_dividend_income' => null,
                'annual_interest_income' => null,
                'annual_other_income' => null,
                'annual_trust_income' => null,
                // Expenditure
                'monthly_expenditure' => null,
                'annual_expenditure' => null,
                // Authentication
                'password' => bcrypt(Str::random(40)),
                'mfa_secret' => null,
                'mfa_recovery_codes' => null,
                'mfa_confirmed_at' => null,
                'mfa_enabled' => false,
                'remember_token' => null,
                // Relationships
                'spouse_id' => null,
                'household_id' => null,
                // Subscription
                'plan' => 'free',
                'trial_ends_at' => null,
            ]);
        });

        Log::info('User data purged', [
            'user_id' => $userId,
            'tables_purged' => $tablesPurged,
            'records_deleted' => $totalDeleted,
        ]);

        return [
            'tables_purged' => $tablesPurged,
            'records_deleted' => $totalDeleted,
        ];
    }

    /**
     * Null out references to this user in other users' records.
     * DB ON DELETE SET NULL handles joint_owner_id automatically,
     * but we handle non-FK references explicitly.
     */
    private function cleanupReverseReferences(int $userId): void
    {
        // Other users who have this user as their spouse
        DB::table('users')->where('spouse_id', $userId)->update(['spouse_id' => null]);

        // Family members linked to this user in other users' families
        DB::table('family_members')->where('linked_user_id', $userId)->update(['linked_user_id' => null]);

        // DC pensions naming this user as beneficiary
        DB::table('dc_pensions')->where('beneficiary_id', $userId)->update([
            'beneficiary_id' => null,
            'beneficiary_name' => null,
        ]);

        // Bequests naming this user as beneficiary
        DB::table('bequests')->where('beneficiary_user_id', $userId)->update([
            'beneficiary_user_id' => null,
        ]);

        // Spouse permissions where this user is the spouse side
        DB::table('spouse_permissions')->where('spouse_id', $userId)->delete();
    }

    /**
     * Delete physical document files from disk before deleting DB records.
     */
    private function deleteDocumentFiles(int $userId): void
    {
        $documents = DB::table('documents')->where('user_id', $userId)->get(['disk', 'path']);

        foreach ($documents as $doc) {
            try {
                Storage::disk($doc->disk)->delete($doc->path);
            } catch (\Exception $e) {
                Log::warning('Failed to delete document file during purge', [
                    'user_id' => $userId,
                    'path' => $doc->path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Try to remove the user's document directory
        try {
            Storage::disk('local')->deleteDirectory("documents/{$userId}");
        } catch (\Exception $e) {
            // Directory may not exist
        }
    }

    /**
     * Delete polymorphic Holdings belonging to this user's InvestmentAccounts and DCPensions.
     */
    private function deleteHoldings(int $userId): int
    {
        $investmentAccountIds = DB::table('investment_accounts')
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();

        $dcPensionIds = DB::table('dc_pensions')
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();

        $deleted = 0;

        if (! empty($investmentAccountIds)) {
            $deleted += DB::table('holdings')
                ->where('holdable_type', 'App\\Models\\Investment\\InvestmentAccount')
                ->whereIn('holdable_id', $investmentAccountIds)
                ->delete();
        }

        if (! empty($dcPensionIds)) {
            $deleted += DB::table('holdings')
                ->where('holdable_type', 'App\\Models\\DCPension')
                ->whereIn('holdable_id', $dcPensionIds)
                ->delete();
        }

        return $deleted;
    }

    /**
     * Tables to delete from, ordered to respect foreign key constraints.
     * Leaf tables first, then parent tables.
     */
    private function getDeletionOrder(): array
    {
        return [
            // ── Leaf records (no other tables depend on these) ──
            'goal_contributions',
            'life_event_allocations',
            'joint_account_logs',
            'document_extraction_logs',
            'rebalancing_actions',
            'investment_recommendations',
            'investment_scenarios',
            'investment_goals',
            'isa_allowance_tracking',
            'recommendation_tracking',
            'savings_goals',

            // ── Estate children ──
            'bequests',
            'gifts',
            'assets',

            // ── Estate parents ──
            'wills',
            'iht_calculations',
            'iht_profiles',
            'trusts',
            'liabilities',
            'letters_to_spouse',

            // ── Goals & Life Events ──
            'life_events',
            'goals',

            // ── Protection policies ──
            'life_insurance_policies',
            'critical_illness_policies',
            'income_protection_policies',
            'disability_policies',
            'sickness_illness_policies',
            'protection_profiles',

            // ── Savings ──
            'savings_accounts',

            // ── Investment ──
            'risk_profiles',
            'investment_plans',
            'investment_accounts',

            // ── Retirement ──
            'dc_pensions',
            'db_pensions',
            'state_pensions',
            'retirement_profiles',

            // ── Property ──
            'mortgages',
            'properties',

            // ── Cash ──
            'cash_accounts',
            'personal_accounts',

            // ── Business ──
            'business_interests',
            'chattels',

            // ── Documents ──
            'documents',

            // ── Profile / Household ──
            'family_members',
            'spouse_permissions',
            'expenditure_profiles',
            'user_assumptions',
            'onboarding_progress',

            // ── Auth / Sessions ──
            'password_reset_sessions',
            'email_verification_codes',
            'user_sessions',
            'user_consents',
            'data_exports',
            'erasure_requests',

            // ── Subscription / Billing ──
            'data_retention_email_log',
            'renewal_reminder_log',
            'trial_reminder_log',
            'payments',
            'subscriptions',
        ];
    }
}
