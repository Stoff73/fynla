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
use App\Support\SavingsInterestRate;
use App\Traits\CalculatesOwnershipShare;

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
    use CalculatesOwnershipShare;

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

        // The spouse's own income and savings must be known (their records,
        // or the campaign's answers), so their side is priced, not assumed.
        if ($mode !== 'single_earner_couple'
            || ! ($this->math->partnerTaxPosition($user, $mode, $household)['savings_known'] ?? false)) {
            return [];
        }

        // M11 — PSA depends on HMRC band over TOTAL taxable income, not
        // employment alone. A £100k employee with £40k of dividends is an
        // additional-rate taxpayer (£140k > £125,140) and gets PSA = £0, so
        // the strategy must skip them even if their employment-only band is
        // 'higher'.
        $userBand = $this->math->bandFromIncomeFor($user, $this->math->taxableIncomeFor($user));
        if ($userBand === 'additional') {
            return [];
        }

        // Sole-name non-ISA savings — shared accounts are already split 50/50
        // by HMRC default and cannot benefit further. "Sole" is decided by
        // ownership_type, not joint_owner_id: the campaign captures joint
        // accounts with a null co-owner User (spouse lives in household
        // input), which a whereNull filter would wrongly count as sole.
        $soleSavings = app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->reject(fn ($acc) => $this->isSharedOwnership($acc))
            ->where('is_isa', false);

        if ($soleSavings->isEmpty()) {
            return [];
        }

        $balance = (float) $soleSavings->sum('current_balance');
        $interest = (float) $soleSavings->sum(fn ($acc) => (float) $acc->current_balance * SavingsInterestRate::fraction($acc->interest_rate));
        $userPsa = $this->math->psaForBand($userBand);

        if ($interest <= $userPsa || $balance <= 0) {
            return [];
        }

        $spousePsa = $this->math->psaForBand('basic');
        $userRate = $this->math->bandRateForBand($userBand);
        $interestPerPerson = $interest / 2;
        // Both sides priced by the tax engine on each partner's whole income
        // (TaxStrategyMath::savingsMoveToPartner): a spouse with a pension has
        // used some of their allowances already (TODO item 4).
        $move = $this->math->savingsMoveToPartner($user, $mode, $household, $interest, $context->pensionPaidElsewhere, 0.0, $interestPerPerson);
        $saving = max(0.0, floor($move['saving'] ?? 0.0));
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
                'Your £%s of sole-name cash is expected to earn about £%s a year. A genuine 50/50 joint holding would allocate about £%s of interest to each of you; on both your incomes, that could save around £%s a year. Confirm ownership and any other spouse savings income before acting.',
                number_format((int) $balance),
                number_format((int) round($interest)),
                number_format((int) round($interestPerPerson)),
                number_format((int) floor($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            requiresAdvice: true,
            extra: [
                'sole_balance' => round($balance, 2),
                'annual_interest' => round($interest, 2),
                'user_psa' => $userPsa,
                'spouse_psa' => $spousePsa,
                'shelterable_interest' => round($shelterableSlice, 2),
                // What the saving above takes out of the user's income, read
                // when the plan re-prices its pension items.
                'interest_removed_from_income' => round($interestPerPerson, 2),
                'pension_paid_first' => round($context->pensionPaidElsewhere, 2),
            ],
        )];
    }
}
