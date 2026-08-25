<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Models\User;
use App\Support\SharedOwnership;
use Carbon\Carbon;

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
            'lender_name' => $data['lender_name'] ?? $data['lender'] ?? null,
            'mortgage_type' => $data['mortgage_type'] ?? 'repayment',
            'original_loan_amount' => $data['original_loan_amount'] ?? $data['original_amount'] ?? null,
            'outstanding_balance' => $data['outstanding_balance'] ?? $data['outstanding_mortgage'] ?? $data['balance'] ?? 0,
            'interest_rate' => $data['interest_rate'] ?? $data['rate'] ?? 0.0,
            'rate_type' => $data['rate_type'] ?? 'fixed',
            'monthly_payment' => $data['monthly_payment'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'maturity_date' => $data['maturity_date'] ?? null,
            'remaining_term_months' => $data['remaining_term_months'] ?? null,
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

        // ownership_percentage — one rule, one home (App\Support\SharedOwnership).
        $data = SharedOwnership::applyTo($data, $ownership);

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

        return self::reconcileTerm($data);
    }

    /**
     * Keep `remaining_term_months` and `maturity_date` telling the same story.
     *
     * A row carrying both a 2039 maturity date and a 300-month term claims two
     * different end dates, and the amortisation schedule and payoff projections
     * run on the term — so a wizard-created mortgage modelled 25 years of debt
     * against a 13-year loan (W-0012). The maturity date is the fact the user
     * entered, so the term is derived from it; where only a term is known the
     * maturity date is derived from the term instead.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function reconcileTerm(array $data): array
    {
        $months = self::monthsUntil($data['maturity_date'] ?? null);

        if ($months !== null) {
            $data['remaining_term_months'] = $months;

            return $data;
        }

        // A partial update that mentions neither field leaves both alone.
        if (! array_key_exists('remaining_term_months', $data) && ! array_key_exists('maturity_date', $data)) {
            return $data;
        }

        // No usable maturity date. Express whatever term we have as a date so the
        // two never diverge. mortgages.remaining_term_months is NOT NULL, so an
        // ingest that supplies neither (Fyn, which maps optional fields to nulls)
        // falls back to the configured default rather than a literal.
        $term = $data['remaining_term_months'] ?? (int) config('mortgage.default_term_months', 300);

        $data['remaining_term_months'] = (int) $term;
        $data['maturity_date'] = $data['maturity_date']
            ?? Carbon::now()->startOfDay()->addMonths((int) $term)->toDateString();

        return $data;
    }

    /**
     * Whole months from today to the given maturity date, floored at zero.
     * Returns null when there is no parseable date to work from.
     */
    private static function monthsUntil(mixed $maturityDate): ?int
    {
        if ($maturityDate === null || $maturityDate === '') {
            return null;
        }

        try {
            $maturity = Carbon::parse($maturityDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return max(0, (int) Carbon::now()->startOfDay()->diffInMonths($maturity, false));
    }
}
