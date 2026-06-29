<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Services\TaxConfigService;

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
     * Map document-extraction output to the canonical-array shape.
     * Document extraction never produces ownership info, so we default
     * to individual ownership at 100% — the user can edit afterwards.
     * Source-document linkage (e.g. source_document_id) is handled by
     * the upload controller after the store call returns the account.
     */
    public function fromUpload(array $extraction): array
    {
        $canonical = [
            'account_name' => $extraction['account_name'] ?? $extraction['institution'] ?? 'Imported account',
            'account_type' => $extraction['account_type'] ?? 'easy_access',
            'institution' => $extraction['institution'] ?? ($extraction['account_name'] ?? null),
            'current_balance' => (float) ($extraction['current_balance'] ?? 0),
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
            'country' => $extraction['country'] ?? 'United Kingdom',
        ];

        foreach (['interest_rate', 'is_isa', 'is_emergency_fund', 'access_type'] as $optional) {
            if (array_key_exists($optional, $extraction)) {
                $canonical[$optional] = $extraction[$optional];
            }
        }

        // ISA → UK enforced
        if (! empty($canonical['is_isa'])) {
            $canonical['country'] = 'United Kingdom';
        }

        return $canonical;
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
        // An untyped account is a current/bank account by default, not a savings
        // product (CSJ: only store a savings type when the user stipulates one).
        $accountType = $toolParams['account_type'] ?? 'current_account';

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
        // Current-tax-year ISA subscriptions, when the user states them
        // ("about £100 this year"). The year label is stamped server-side —
        // the model never supplies tax-year strings. Without this field the
        // ISA top-up strategy falls back to the created-this-year proxy and
        // a freshly-captured ISA's full balance masquerades as subscriptions
        // (live-browser finding, 2026-06-11).
        if ($isIsa && isset($toolParams['isa_subscription_amount'])) {
            $canonical['isa_subscription_amount'] = (float) $toolParams['isa_subscription_amount'];
            $canonical['isa_subscription_year'] = app(TaxConfigService::class)->getTaxYear();
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
