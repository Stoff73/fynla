<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Models\User;
use App\Support\SharedOwnership;

/**
 * InvestmentAccountNormaliser — translates upstream ingest shapes into a
 * canonical payload accepted by InvestmentAccountStore::create / ::update.
 *
 * Investment accounts do NOT support ownership_type=tenants_in_common.
 * The normaliser coerces TIC → joint at the boundary. The store's
 * validateCanonical enforces the enum strictly (rejects TIC at the store layer).
 */
final class InvestmentAccountNormaliser
{
    /**
     * Every `investment_accounts` column that is NOT NULL, carries a database
     * default, AND can be reached from a client payload (i.e. appears in the
     * Store/Update request rules).
     *
     * A null for any of these must be DROPPED, never passed through: the column
     * cannot store it, and omitting the key is what lets the default apply.
     *
     * This exists because a per-column special case was not enough. `country` had
     * its own null-drop in both FormRequests (PR #269) and nobody generalised it,
     * so when `advisor_fee_percent` gained a `nullable` validation rule (W-0008)
     * the frontend's explicit null — sent whenever the additional-information
     * panel is collapsed — reached a NOT NULL column and 500'd EVERY investment
     * account create (W-0052).
     *
     * The tester found one field; the reachable surface is 28. Any of them sent
     * as null would have failed identically, so this covers the class rather
     * than the instance. One rule here serves the form, Fyn and upload paths at
     * once, and the schema-drift test in
     * tests/Feature/Investment/InvestmentAccountNotNullColumnsTest.php fails if a
     * column is added or a validation rule newly exposes one.
     *
     * @var list<string>
     */
    public const NOT_NULL_WITH_DEFAULT = [
        'accelerated_vesting_allowed',
        'advisor_fee_percent',
        'clawback_risk',
        'company_status',
        'contribution_frequency',
        'country',
        'csop_disqualifying_event',
        'current_value',
        'employer_is_listed',
        'ers_registered',
        'grant_currency',
        'has_anti_dilution',
        'has_performance_conditions',
        'holding_structure',
        'investment_currency',
        'loss_relief_eligible',
        'negligible_value_claim',
        'ownership_percentage',
        'ownership_type',
        'paye_via_payroll',
        'platform_fee_frequency',
        'platform_fee_type',
        'scheme_status',
        'units_exercised',
        'units_expired',
        'units_forfeited',
        'units_unvested',
        'units_vested',
    ];

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
            'account_name' => $data['account_name'] ?? $data['name'] ?? null,
            'account_type' => $data['account_type'] ?? $data['type'] ?? null,
            'provider' => $data['provider'] ?? null,
            'platform' => $data['platform'] ?? null,
            'current_value' => $data['current_value'] ?? $data['value'] ?? $data['balance'] ?? 0,
            'contributions_ytd' => $data['contributions_ytd'] ?? $data['contributions'] ?? null,
            'monthly_contribution_amount' => $data['monthly_contribution_amount'] ?? $data['monthly_contribution'] ?? null,
            'contribution_frequency' => $data['contribution_frequency'] ?? null,
            'isa_type' => $data['isa_type'] ?? null,
            'isa_subscription_current_year' => $data['isa_subscription_current_year'] ?? null,
            'ownership_type' => $data['ownership_type'] ?? $data['ownership'] ?? 'individual',
            'ownership_percentage' => $data['ownership_percentage'] ?? null,
            'joint_owner_id' => $data['joint_owner_id'] ?? null,
            'joint_owner_name' => $data['joint_owner_name'] ?? null,
            'country' => $data['country'] ?? null,
            'include_in_retirement' => $data['include_in_retirement'] ?? null,
        ];

        // Pass through any extra fields not explicitly mapped (account-type-specific)
        foreach ($data as $key => $value) {
            if (! array_key_exists($key, $mapped)) {
                $mapped[$key] = $value;
            }
        }

        // Fyn maps optional fields to explicit nulls above; strip them so they
        // do not override NOT NULL DB defaults on insert. fromForm/fromUpload
        // are untouched, preserving the ability to clear a column on update.
        $mapped = array_filter($mapped, static fn ($v) => $v !== null);

        return self::normalise($mapped, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromUpload(array $data, User $user): array
    {
        // Upload field-mapper produces canonical shape already.
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

        // tenants_in_common → joint coercion (investment accounts don't support TIC)
        $ownership = $data['ownership_type'] ?? 'individual';
        if ($ownership === 'tenants_in_common') {
            $ownership = 'joint';
        }
        // The fallback keeps junk out, but it used to list only individual and
        // joint — so `trust`, which the column stores and both requests permit, was
        // silently rewritten to `individual`. A caller saying an account is held in
        // trust was told it saved and recorded as solely owned, with no error
        // (W-0329). Only the TIC coercion above was ever a decision; this was
        // collateral. SharedOwnership treats trust as unshared, so the percentage
        // still resolves to 100 for the owner, as it does on savings and liabilities.
        if (! in_array($ownership, ['individual', 'joint', 'trust'], true)) {
            $ownership = 'individual';
        }
        $data['ownership_type'] = $ownership;

        // ownership_percentage — one rule, one home (App\Support\SharedOwnership).
        $data = SharedOwnership::applyTo($data, $ownership);

        // Drop nulls that a NOT NULL column cannot take, so its default applies.
        // Must run BEFORE the casts below — (float) null is 0.0, which would
        // silently write a zero where the caller meant "leave it alone".
        foreach (self::NOT_NULL_WITH_DEFAULT as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === null || $data[$field] === '')) {
                unset($data[$field]);
            }
        }

        // Cast core currency/numeric fields to float
        foreach (['current_value', 'contributions_ytd', 'monthly_contribution_amount', 'planned_lump_sum_amount', 'isa_subscription_current_year', 'platform_fee_percent', 'platform_fee_amount', 'advisor_fee_percent'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = (float) $data[$field];
            }
        }

        return $data;
    }
}
