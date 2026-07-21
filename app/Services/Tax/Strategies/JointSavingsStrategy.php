<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #15 — Joint Savings Split for Personal Savings Allowance Doubling.
 *
 * Fires only when a single-earner household has explicitly confirmed that
 * the non-earning spouse has no existing savings. That is the only campaign
 * branch where the spouse's current savings-income use is known well enough
 * to calculate a 50/50 split without manufacturing allowance headroom.
 */
final class JointSavingsStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $mode = $context->mode;
        $household = $context->household;

        // M10 — civil partners are tax-equivalent to married couples for
        // joint-savings interest splits. Treat 'civil_partnership' the same
        // as 'married' so partners aren't excluded from joint-savings
        // suggestions.
        $isPartnered = in_array($user->marital_status, ['married', 'civil_partnership'], true)
            || in_array($mode, ['dual_earner', 'single_earner_couple'], true);
        if (! $isPartnered) {
            return [];
        }

        if ($mode !== 'single_earner_couple'
            || $household === null
            || $household->spouse_existing_savings_balance === null
            || (float) $household->spouse_existing_savings_balance !== 0.0) {
            return [];
        }

        // M11 — PSA depends on HMRC band over TOTAL taxable income, not
        // employment alone. A £100k employee with £40k of dividends is an
        // additional-rate taxpayer (£140k > £125,140) and gets PSA = £0, so
        // the strategy must skip them even if their employment-only band is
        // 'higher'.
        $userBand = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        if ($userBand === 'additional') {
            return [];
        }

        // Sole-name non-ISA savings — joint accounts (joint_owner_id set) are
        // already split 50/50 by HMRC default and cannot benefit further.
        // forUser() is joint-aware; the Collection-level where('user_id') keeps
        // strictly single-owner semantics (whereNull('joint_owner_id') further
        // excludes joint accounts owned by this user).
        $soleSavings = app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->whereNull('joint_owner_id')
            ->where('is_isa', false);

        if ($soleSavings->isEmpty()) {
            return [];
        }

        $balance = (float) $soleSavings->sum('current_balance');
        $interest = (float) $soleSavings->sum(function ($acc) {
            $r = (float) $acc->interest_rate;
            if ($r > 1) {
                $r /= 100;
            }

            return (float) $acc->current_balance * $r;
        });
        $userPsa = $this->math->psaForBand($userBand);

        if ($interest <= $userPsa || $balance <= 0) {
            return [];
        }

        $income = $this->taxConfig->getIncomeTax();
        $spousePsa = $this->math->psaForBand('basic');
        $spousePersonalAllowance = $this->math->personalAllowanceForIncome(0.0);
        $spouseStartingRate = (float) ($income['starting_rate_for_savings']['band']
            ?? $income['starting_rate_for_savings']['amount']
            ?? 0);
        $spouseTaxFreeInterestCapacity = $spousePersonalAllowance + $spouseStartingRate + $spousePsa;
        $userRate = $this->math->bandRateForBand($userBand);
        $spouseRate = $this->math->bandRateForBand('basic');
        $interestPerPerson = $interest / 2;
        $taxBefore = max(0.0, $interest - $userPsa) * $userRate;
        $taxAfter = max(0.0, $interestPerPerson - $userPsa) * $userRate
            + max(0.0, $interestPerPerson - $spouseTaxFreeInterestCapacity) * $spouseRate;
        $saving = max(0.0, $taxBefore - $taxAfter);
        $shelterableSlice = $userRate > 0 ? $saving / $userRate : 0.0;

        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'joint_savings_psa_split',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Low,
            title: 'Consider sharing savings equally to use both partners\' tax positions',
            description: sprintf(
                'Your £%s of sole-name cash is expected to earn about £%s a year. A genuine 50/50 joint holding would allocate about £%s of interest to each of you; with no spouse savings on file, that could save around £%s a year. Confirm ownership and any other spouse savings income before acting.',
                number_format((int) $balance),
                number_format((int) round($interest)),
                number_format((int) round($interestPerPerson)),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            requiresAdvice: true,
            extra: [
                'sole_balance' => round($balance, 2),
                'annual_interest' => round($interest, 2),
                'user_psa' => $userPsa,
                'spouse_psa' => $spousePsa,
                'shelterable_interest' => round($shelterableSlice, 2),
            ],
        )];
    }
}
