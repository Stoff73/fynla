<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;

/**
 * Aggregates module status per user across all models.
 *
 * Queries existing relationships (no new tables required) to determine
 * which modules a user has populated, partially populated, or left empty.
 */
class UserModuleTrackingService
{
    /**
     * Get full module status for a user.
     *
     * @return array Keyed by module name with status, sub_areas, and onboarding data
     */
    public function getModuleStatus(User $user): array
    {
        $user->loadMissing([
            // Protection
            'lifeInsurancePolicies', 'criticalIllnessPolicies',
            'incomeProtectionPolicies', 'disabilityPolicies', 'sicknessIllnessPolicies',
            // Savings
            'cashAccounts', 'savingsAccounts',
            // Investment
            'investmentAccounts.holdings',
            // Retirement
            'dcPensions', 'dbPensions', 'statePension', 'retirementProfile',
            // Estate
            'trusts', 'assets', 'gifts', 'lastingPowersOfAttorney',
            'properties', 'mortgages',
            // Onboarding
            'onboardingProgress',
        ]);

        $journeyStates = $user->journey_states ?? [];

        return [
            'protection' => $this->getProtectionStatus($user, $journeyStates),
            'savings' => $this->getSavingsStatus($user, $journeyStates),
            'investment' => $this->getInvestmentStatus($user, $journeyStates),
            'retirement' => $this->getRetirementStatus($user, $journeyStates),
            'estate' => $this->getEstateStatus($user, $journeyStates),
            'onboarding' => $this->getOnboardingData($user),
        ];
    }

    // =========================================================================
    // Protection
    // =========================================================================

    private function getProtectionStatus(User $user, array $journeyStates): array
    {
        if ($this->isModuleSkipped('protection', $journeyStates)) {
            return ['status' => 'skipped', 'sub_areas' => $this->getProtectionSubAreas($user)];
        }

        $subAreas = $this->getProtectionSubAreas($user);

        // Key sub-areas: life, critical illness, income protection
        $keyAreas = [
            $subAreas['life_insurance']['count'] > 0,
            $subAreas['critical_illness']['count'] > 0,
            $subAreas['income_protection']['count'] > 0,
        ];

        return [
            'status' => $this->determineStatus($keyAreas),
            'sub_areas' => $subAreas,
        ];
    }

    private function getProtectionSubAreas(User $user): array
    {
        return [
            'life_insurance' => [
                'count' => $user->lifeInsurancePolicies->count(),
                'total_cover' => (float) $user->lifeInsurancePolicies->sum('sum_assured'),
            ],
            'critical_illness' => [
                'count' => $user->criticalIllnessPolicies->count(),
            ],
            'income_protection' => [
                'count' => $user->incomeProtectionPolicies->count(),
            ],
            'disability' => [
                'count' => $user->disabilityPolicies->count(),
            ],
            'sickness_illness' => [
                'count' => $user->sicknessIllnessPolicies->count(),
            ],
        ];
    }

    // =========================================================================
    // Savings
    // =========================================================================

    private function getSavingsStatus(User $user, array $journeyStates): array
    {
        if ($this->isModuleSkipped('savings', $journeyStates)) {
            return ['status' => 'skipped', 'sub_areas' => $this->getSavingsSubAreas($user)];
        }

        $subAreas = $this->getSavingsSubAreas($user);

        // Key sub-areas: any account and ISA
        $hasAnyAccount = $subAreas['cash_accounts']['count'] > 0 || $subAreas['savings_accounts']['count'] > 0;
        $hasIsa = $subAreas['isa_accounts']['count'] > 0;

        $keyAreas = [$hasAnyAccount, $hasIsa];

        return [
            'status' => $this->determineStatus($keyAreas),
            'sub_areas' => $subAreas,
        ];
    }

    private function getSavingsSubAreas(User $user): array
    {
        $isaAccounts = $user->savingsAccounts->filter(function ($account) {
            return ($account->account_type ?? '') === 'isa';
        });

        return [
            'cash_accounts' => [
                'count' => $user->cashAccounts->count(),
                'total_balance' => (float) $user->cashAccounts->sum('current_balance'),
            ],
            'savings_accounts' => [
                'count' => $user->savingsAccounts->count(),
                'total_balance' => (float) $user->savingsAccounts->sum('current_balance'),
            ],
            'isa_accounts' => [
                'count' => $isaAccounts->count(),
            ],
            'emergency_fund' => [
                'exists' => $user->cashAccounts->count() > 0 || $user->savingsAccounts->count() > 0,
            ],
        ];
    }

    // =========================================================================
    // Investment
    // =========================================================================

    private function getInvestmentStatus(User $user, array $journeyStates): array
    {
        if ($this->isModuleSkipped('investment', $journeyStates)) {
            return ['status' => 'skipped', 'sub_areas' => $this->getInvestmentSubAreas($user)];
        }

        $subAreas = $this->getInvestmentSubAreas($user);

        // Key sub-areas: at least one account, risk profile set
        $hasAccounts = $subAreas['investment_accounts']['count'] > 0;
        $hasRiskProfile = $subAreas['risk_profile']['exists'];

        $keyAreas = [$hasAccounts, $hasRiskProfile];

        return [
            'status' => $this->determineStatus($keyAreas),
            'sub_areas' => $subAreas,
        ];
    }

    private function getInvestmentSubAreas(User $user): array
    {
        $holdings = $user->investmentAccounts->flatMap(function ($account) {
            return $account->holdings ?? collect();
        });

        $hasRiskProfile = $user->investmentAccounts->contains(function ($account) {
            return $account->risk_profile !== null;
        });

        return [
            'investment_accounts' => [
                'count' => $user->investmentAccounts->count(),
                'total_value' => (float) $user->investmentAccounts->sum('current_value'),
            ],
            'holdings' => [
                'count' => $holdings->count(),
            ],
            'risk_profile' => [
                'exists' => $hasRiskProfile,
            ],
            'investment_goals' => [
                'count' => 0, // Goals tracked separately
            ],
        ];
    }

    // =========================================================================
    // Retirement
    // =========================================================================

    private function getRetirementStatus(User $user, array $journeyStates): array
    {
        if ($this->isModuleSkipped('retirement', $journeyStates)) {
            return ['status' => 'skipped', 'sub_areas' => $this->getRetirementSubAreas($user)];
        }

        $subAreas = $this->getRetirementSubAreas($user);

        // Key sub-areas: at least one pension, state pension or profile
        $hasPensions = $subAreas['dc_pensions']['count'] > 0 || $subAreas['db_pensions']['count'] > 0;
        $hasProfile = $subAreas['state_pension']['exists'] || $subAreas['retirement_profile']['exists'];

        $keyAreas = [$hasPensions, $hasProfile];

        return [
            'status' => $this->determineStatus($keyAreas),
            'sub_areas' => $subAreas,
        ];
    }

    private function getRetirementSubAreas(User $user): array
    {
        return [
            'dc_pensions' => [
                'count' => $user->dcPensions->count(),
                'total_fund_value' => (float) $user->dcPensions->sum('current_fund_value'),
            ],
            'db_pensions' => [
                'count' => $user->dbPensions->count(),
            ],
            'state_pension' => [
                'exists' => $user->statePension !== null,
            ],
            'retirement_profile' => [
                'exists' => $user->retirementProfile !== null,
            ],
        ];
    }

    // =========================================================================
    // Estate
    // =========================================================================

    private function getEstateStatus(User $user, array $journeyStates): array
    {
        if ($this->isModuleSkipped('estate', $journeyStates)) {
            return ['status' => 'skipped', 'sub_areas' => $this->getEstateSubAreas($user)];
        }

        $subAreas = $this->getEstateSubAreas($user);

        // Key sub-areas: will, LPA, trusts or assets
        $hasWill = $subAreas['will']['exists'];
        $hasLpa = $subAreas['lasting_powers_of_attorney']['count'] > 0;
        $hasTrustsOrAssets = $subAreas['trusts']['count'] > 0 || $subAreas['assets']['count'] > 0;

        $keyAreas = [$hasWill, $hasLpa, $hasTrustsOrAssets];

        return [
            'status' => $this->determineStatus($keyAreas),
            'sub_areas' => $subAreas,
        ];
    }

    private function getEstateSubAreas(User $user): array
    {
        // Check for will via the Estate\Will model
        $hasWill = \App\Models\Estate\Will::where('user_id', $user->id)
            ->where('has_will', true)
            ->exists();

        return [
            'will' => [
                'exists' => $hasWill,
            ],
            'lasting_powers_of_attorney' => [
                'count' => $user->lastingPowersOfAttorney->count(),
            ],
            'trusts' => [
                'count' => $user->trusts->count(),
                'total_value' => (float) $user->trusts->sum('current_value'),
            ],
            'gifts' => [
                'count' => $user->gifts->count(),
            ],
            'assets' => [
                'count' => $user->assets->count(),
            ],
        ];
    }

    // =========================================================================
    // Onboarding
    // =========================================================================

    private function getOnboardingData(User $user): array
    {
        return [
            'completed' => (bool) $user->onboarding_completed,
            'started_at' => $user->onboarding_started_at?->toIso8601String(),
            'completed_at' => $user->onboarding_completed_at?->toIso8601String(),
            'journey_states' => $user->journey_states ?? [],
            'journey_selections' => $user->journey_selections ?? [],
            'progress_records' => $user->onboardingProgress->count(),
            'life_stage' => $user->life_stage,
            'life_stage_completed_steps' => $user->life_stage_completed_steps ?? [],
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Determine status from an array of booleans (one per key sub-area).
     * All true = complete, some true = partial, none true = empty.
     */
    private function determineStatus(array $keyAreas): string
    {
        $filled = array_filter($keyAreas);

        if (count($filled) === 0) {
            return 'empty';
        }

        if (count($filled) === count($keyAreas)) {
            return 'complete';
        }

        return 'partial';
    }

    /**
     * Check if a module is marked as skipped in the user's journey states.
     */
    private function isModuleSkipped(string $module, array $journeyStates): bool
    {
        return ($journeyStates[$module] ?? null) === 'skipped';
    }
}
