<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;

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

        // Sole-name non-ISA savings, from the one place the gift reads them
        // (TaxStrategyMath::soleNonIsaSavings): a joint account is already
        // taxed half each (ITA 2007 s836).
        ['balance' => $balance, 'interest' => $interest] = $this->math->soleNonIsaSavings($user);
        if ($balance <= 0) {
            return [];
        }
        $userPsa = $this->math->psaForBand($userBand);

        if ($interest <= $userPsa || $balance <= 0) {
            return [];
        }

        $interestPerPerson = $interest / 2;
        // Both sides priced by the tax engine on each partner's whole income
        // (TaxStrategyMath::savingsMoveToPartner): a spouse with a pension has
        // used some of their allowances already (TODO item 4).
        $move = $this->math->savingsMoveToPartner($user, $mode, $household, $interest, $context->pensionPaidElsewhere, 0.0, $interestPerPerson);
        $saving = max(0.0, floor($move['saving'] ?? 0.0));

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
                // What the saving above takes out of the user's income, read
                // when the plan re-prices its pension items.
                'interest_removed_from_income' => round($interestPerPerson, 2),
                'pension_paid_first' => round($context->pensionPaidElsewhere, 2),
            ],
        )];
    }
}
