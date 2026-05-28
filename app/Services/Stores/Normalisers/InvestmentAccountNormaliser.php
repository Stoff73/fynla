<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Models\User;

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
        if (! in_array($ownership, ['individual', 'joint'], true)) {
            $ownership = 'individual';
        }
        $data['ownership_type'] = $ownership;

        // ownership_percentage default
        if (! isset($data['ownership_percentage']) || $data['ownership_percentage'] === null) {
            $data['ownership_percentage'] = $ownership === 'joint' ? 50.00 : 100.00;
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
