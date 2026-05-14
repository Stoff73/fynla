<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

class SavingsAccountNormaliser
{
    /**
     * Map HTTP form-validated input to the canonical-array shape consumed
     * by SavingsStore::create(). Replicates the ownership / country logic
     * that previously lived in SavingsController::storeAccount (lines 266-285).
     */
    public function fromForm(array $request): array
    {
        $data = $request;

        $data['ownership_type'] = $data['ownership_type'] ?? 'individual';

        if (! isset($data['ownership_percentage'])) {
            $data['ownership_percentage'] = 100.00;
        }

        if ($data['ownership_type'] === 'joint' && (float) $data['ownership_percentage'] === 100.00) {
            $data['ownership_percentage'] = 50.00;
        }

        // Reset ownership to sole when switching back to individual.
        // Forces both fields regardless of what the caller passed — matches
        // SavingsController::updateAccount (lines 387-391).
        if ($data['ownership_type'] === 'individual') {
            $data['joint_owner_id'] = null;
            $data['ownership_percentage'] = 100.00;
        }

        // ISA accounts must always be United Kingdom.
        // Non-ISA accounts default to United Kingdom if not provided.
        if (! empty($data['is_isa'])) {
            $data['country'] = 'United Kingdom';
        } elseif (! isset($data['country']) || $data['country'] === null) {
            $data['country'] = 'United Kingdom';
        }

        return $data;
    }

    /**
     * Map Fyn AI tool params to the canonical-array shape consumed by
     * SavingsStore::create(). Replicates the AI-enum-to-DB-value mapping
     * and ISA inference that previously lived in
     * CoordinatingAgent::handleCreateSavingsAccount.
     */
    public function fromFyn(array $toolParams): array
    {
        $isIsa = (bool) ($toolParams['is_isa'] ?? false);
        $accountType = $toolParams['account_type'] ?? 'easy_access';

        $dbAccountType = match ($accountType) {
            'fixed_term' => 'fixed',
            'regular_saver' => 'easy_access',
            default => $accountType,
        };

        if ($isIsa && ! in_array($dbAccountType, ['cash_isa', 'junior_isa'], true)) {
            $dbAccountType = 'cash_isa';
        }

        $accessType = match ($dbAccountType) {
            'notice' => 'notice',
            'fixed' => 'fixed',
            default => 'immediate',
        };

        $canonical = [
            'account_name' => $toolParams['account_name'],
            'institution' => ! empty($toolParams['institution']) ? $toolParams['institution'] : $toolParams['account_name'],
            'account_type' => $dbAccountType,
            'current_balance' => (float) $toolParams['current_balance'],
            'access_type' => $accessType,
            'is_isa' => $isIsa,
            'is_emergency_fund' => (bool) ($toolParams['is_emergency_fund'] ?? false),
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
        ];

        if (isset($toolParams['interest_rate'])) {
            $canonical['interest_rate'] = (float) $toolParams['interest_rate'];
        }
        if (isset($toolParams['regular_contribution_amount'])) {
            $canonical['regular_contribution_amount'] = (float) $toolParams['regular_contribution_amount'];
        }

        // ISA / non-ISA country default — same rule as fromForm
        if ($isIsa) {
            $canonical['country'] = 'United Kingdom';
        } else {
            $canonical['country'] = $toolParams['country'] ?? 'United Kingdom';
        }

        return $canonical;
    }
}
