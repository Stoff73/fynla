<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

class FSCSAssessor
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Assess FSCS exposure across all savings accounts
     */
    public function assessExposure(Collection $accounts, bool $isJointAccount = false): array
    {
        $limit = (float) ($isJointAccount
            ? $this->taxConfig->getSavingsConfig('fscs_joint_protection')
            : $this->taxConfig->getSavingsConfig('fscs_deposit_protection'));

        $groups = $this->groupByInstitution($accounts);

        $exposures = [];
        foreach ($groups as $institutionGroup => $groupAccounts) {
            $totalBalance = $groupAccounts->sum('current_balance');
            $isBreach = $totalBalance > $limit;
            $isApproaching = ! $isBreach && $totalBalance > ($limit * 0.88); // 88% = approaching

            $exposures[] = [
                'institution_group' => $institutionGroup,
                'accounts' => $groupAccounts->pluck('id')->toArray(),
                'account_names' => $groupAccounts->pluck('account_name')->toArray(),
                'total_balance' => round($totalBalance, 2),
                'fscs_limit' => $limit,
                'excess' => round(max(0, $totalBalance - $limit), 2),
                'headroom' => round(max(0, $limit - $totalBalance), 2),
                'is_breach' => $isBreach,
                'is_approaching' => $isApproaching,
            ];
        }

        return [
            'fscs_limit' => $limit,
            'institution_groups' => $exposures,
            'has_breach' => collect($exposures)->contains('is_breach', true),
            'has_approaching' => collect($exposures)->contains('is_approaching', true),
            'total_at_risk' => collect($exposures)->where('is_breach', true)->sum('excess'),
        ];
    }

    /**
     * Group accounts by banking licence (institution group)
     */
    private function groupByInstitution(Collection $accounts): Collection
    {
        $licenceGroups = config('banking_licence_groups', []);

        return $accounts->groupBy(function ($account) use ($licenceGroups) {
            $institution = strtolower(trim($account->institution ?? 'unknown'));

            // Check if this institution belongs to a shared licence group
            foreach ($licenceGroups as $groupName => $members) {
                $members = array_map('strtolower', $members);
                if (in_array($institution, $members)) {
                    return $groupName;
                }
            }

            return $account->institution ?? 'Unknown';
        });
    }
}
