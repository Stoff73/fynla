<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Will;
use App\Models\FamilyMember;
use App\Models\Investment\RiskProfile;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Traits\ResolvesExpenditure;

/**
 * The required_data availability vocabulary for the five non-tax modules —
 * the analogue of HouseholdFinancialContext::availability() (which owns the 13
 * tax keys). Each key answers "is the data behind this strategy available?";
 * a strategy whose required_data are not all true is "locked" (surfaced as an
 * unlock prompt, never silently skipped). The key set returned by forModule()
 * IS the contract that each module's source='strategy' required_data seeds must
 * draw from — they must match exactly or a strategy locks forever.
 */
final class ModuleAvailabilityProvider
{
    use ResolvesExpenditure;

    public function __construct(
        private readonly SavingsStore $savingsStore,
        private readonly InvestmentAccountStore $investmentStore,
        private readonly PensionStore $pensionStore,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function forModule(string $module, User $user): array
    {
        return match ($module) {
            'retirement' => [
                'dc_pension_exists' => $this->hasDcPension($user),
                'pension_input_history' => collect($this->pensionStore->pensionInputHistory($user))->isNotEmpty(),
                'retirement_age_set' => $user->target_retirement_age !== null,
            ],
            'savings' => [
                'emergency_fund_target_known' => $this->expenditureKnown($user),
                'savings_balances' => $this->hasSavingsBalance($user),
            ],
            'investment' => [
                'gia_holdings' => $this->hasGiaHoldings($user),
                'risk_profile_set' => RiskProfile::where('user_id', $user->id)->exists(),
            ],
            'protection' => [
                'dependants_known' => FamilyMember::where('user_id', $user->id)->exists(),
                'life_cover_in_force' => LifeInsurancePolicy::where('user_id', $user->id)->exists(),
                'income_known' => $this->hasAnnualIncome($user),
            ],
            'estate' => [
                'will_in_place' => Will::where('user_id', $user->id)->exists(),
                'lpa_registered' => LastingPowerOfAttorney::where('user_id', $user->id)->exists(),
                'estate_value_known' => $this->hasSavingsBalance($user) || $this->hasGiaHoldings($user),
            ],
            default => [],
        };
    }

    private function hasDcPension(User $user): bool
    {
        return $this->pensionStore->forUserByType($user, 'dc')->isNotEmpty();
    }

    private function hasSavingsBalance(User $user): bool
    {
        return $this->savingsStore->forUser($user)
            ->where('user_id', $user->id)
            ->isNotEmpty();
    }

    /** Non-ISA investment accounts (GIA, bonds, etc.) — mirrors HouseholdFinancialContext. */
    private function hasGiaHoldings(User $user): bool
    {
        return $this->investmentStore->forUser($user)
            ->filter(fn ($a) => (int) $a->user_id === (int) $user->id
                && ($a->account_type === null || $a->account_type !== 'isa'))
            ->isNotEmpty();
    }

    private function hasAnnualIncome(User $user): bool
    {
        return ((float) ($user->annual_employment_income ?? 0)) > 0
            || ((float) ($user->annual_self_employment_income ?? 0)) > 0;
    }

    private function expenditureKnown(User $user): bool
    {
        return ($this->resolveMonthlyExpenditure($user)['source'] ?? 'none') !== 'none';
    }
}
