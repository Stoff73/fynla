<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Models\User;

/**
 * MortgageNormaliser — translates upstream ingest shapes into a canonical
 * payload accepted by MortgageStore::create / ::update.
 *
 * Mortgages do NOT support ownership_type=tenants_in_common. The normaliser
 * coerces TIC → joint at the boundary. The store's validateCanonical
 * enforces the enum strictly (rejects TIC at the store layer).
 */
final class MortgageNormaliser
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromForm(array $data, User $user): array
    {
        return self::normalise($data, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromFyn(array $data, User $user): array
    {
        // Fyn AI passes user-friendly field names; map to canonical schema.
        $mapped = [
            'property_id' => $data['property_id'] ?? null,
            'lender_name' => $data['lender_name'] ?? $data['lender'] ?? 'To be completed',
            'mortgage_type' => $data['mortgage_type'] ?? 'repayment',
            'original_loan_amount' => $data['original_loan_amount'] ?? $data['original_amount'] ?? null,
            'outstanding_balance' => $data['outstanding_balance'] ?? $data['outstanding_mortgage'] ?? $data['balance'] ?? 0,
            'interest_rate' => $data['interest_rate'] ?? $data['rate'] ?? 0.0,
            'rate_type' => $data['rate_type'] ?? 'fixed',
            'monthly_payment' => $data['monthly_payment'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'maturity_date' => $data['maturity_date'] ?? null,
            'remaining_term_months' => $data['remaining_term_months'] ?? 300,
            'ownership_type' => $data['ownership_type'] ?? 'individual',
            'ownership_percentage' => $data['ownership_percentage'] ?? null,
            'joint_owner_id' => $data['joint_owner_id'] ?? null,
            'joint_owner_name' => $data['joint_owner_name'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return self::normalise($mapped, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromUpload(array $data, User $user): array
    {
        // Upload field-mapper (MortgageMapper) produces canonical shape already.
        return self::normalise($data, $user);
    }

    /**
     * Shared normalisation: ownership_type coercion, percentage defaults, casts.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalise(array $data, User $user): array
    {
        $data['user_id'] = $user->id;

        // tenants_in_common → joint coercion (mortgages don't support TIC)
        $ownership = $data['ownership_type'] ?? 'individual';
        if ($ownership === 'tenants_in_common') {
            $ownership = 'joint';
        }
        if (! in_array($ownership, ['individual', 'joint'], true)) {
            $ownership = 'individual';
        }
        $data['ownership_type'] = $ownership;

        // ownership_percentage default
        if (! isset($data['ownership_percentage']) || $data['ownership_percentage'] === null) {
            $data['ownership_percentage'] = $ownership === 'joint' ? 50.00 : 100.00;
        }

        // Cast numeric fields
        foreach (['outstanding_balance', 'original_loan_amount', 'monthly_payment', 'monthly_interest_portion', 'fixed_rate_percentage', 'variable_rate_percentage', 'repayment_percentage', 'interest_only_percentage'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = (float) $data[$field];
            }
        }
        foreach (['interest_rate', 'fixed_interest_rate', 'variable_interest_rate'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = round((float) $data[$field], 4);
            }
        }

        if (isset($data['remaining_term_months'])) {
            $data['remaining_term_months'] = (int) $data['remaining_term_months'];
        }

        return $data;
    }
}
