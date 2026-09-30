<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #12 — Pension contribution for a non-earning spouse.
 *
 * Fires when household_calculation_mode = single_earner_couple AND the
 * spouse is under 75. £2,880 net contribution → £3,600 gross via 25%
 * basic-rate uplift (figures from TaxConfigService) = the direct saving. Spouse age is resolved from
 * a family_members row (relationship in spouse/partner/wife/husband/
 * civil_partner) or, failing that, a linked spouse user — when no DOB is
 * known we keep firing because single_earner_couple normally implies a
 * working-age partner.
 */
final class NonEarnerSpousePensionStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $household = $context->household;

        // CSJ ruling 2026-09-25: the top-up counts as household tax saved
        // because a spouse or civil partner is a legal contract; an unmarried
        // couple is outside that ruling.
        if (! $this->math->isMarriedOrCivilPartner($user)) {
            return [];
        }

        // A partner with their own shared account gets their own pension card
        // on it, sized by their own money (CSJ 2026-09-30: "the partner will
        // get their own card in their own account"): no second top-up here.
        // Nor one before the money to pay it is known: the plan asks for the
        // spending first (CSJ 2026-09-30: "We ask for expenditure").
        if (app(HouseholdFinancialContext::class)->partnerWithOwnRecords($user) !== null
            || $context->pensionMoney === null) {
            return [];
        }

        if ($context->mode === 'single_earner_couple') {
            return $this->nonEarnerPath($user, $household, $context->pensionMoney);
        }

        if ($context->mode === 'dual_earner') {
            return $this->modestEarnerPath($user, $household, $context->pensionMoney);
        }

        return [];
    }

    /**
     * Original non-earner path (single_earner_couple mode): flat £2,880 net
     * net → gross via basic-rate relief at source (TaxStrategyMath::nonEarnerPensionContribution).
     */
    private function nonEarnerPath(User $user, mixed $household, float $money): array
    {
        $spouseAge = $this->resolveSpouseAge($user);
        if ($spouseAge !== null && $spouseAge >= $this->reliefMaxAge()) {
            return [];
        }

        $figures = $this->math->nonEarnerPensionContribution();
        // What the spouse already pays in (stored net for a non-earner, the
        // relief-at-source shape the capture writes) comes off the top (B5).
        $alreadyPaid = (float) ($household?->spouse_pension_input_annual ?? 0);
        // Never more than the household can pay (CSJ 2026-09-30: "affordability check always").
        $netContribution = round(min(max(0.0, $figures['net'] - $alreadyPaid), $money), 2);
        if ($netContribution < 1) {
            return [];
        }
        $governmentUplift = round($netContribution * $figures['relief'] / $figures['net'], 2);
        $existingBalance = (float) ($household?->spouse_existing_pension_balance ?? 0);

        $balanceLine = $existingBalance > 0
            ? sprintf(' On top of their existing £%s pot.', number_format((int) $existingBalance))
            : '';

        return [new StrategyRecommendation(
            type: 'non_earner_spouse_pension',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Top up your spouse\'s pension by £%s — instant £%s of free money',
                number_format((int) $netContribution),
                number_format((int) $governmentUplift),
            ),
            description: sprintf(
                'A £%s contribution to your spouse\'s personal pension is grossed up to £%s by the government, even though they have no earnings. That\'s £%s a year of free uplift, plus a separate 25%% tax-free lump sum and another Personal Allowance in retirement.%s',
                number_format((int) $netContribution),
                number_format((int) ($netContribution + $governmentUplift)),
                number_format((int) $governmentUplift),
                $balanceLine,
            ),
            estimatedAnnualTaxSaved: round($governmentUplift, 2),
            extra: [
                'net_contribution' => $netContribution,
                'gross_contribution' => $netContribution + $governmentUplift,
                'government_uplift' => $governmentUplift,
                'spouse_existing_pension_balance' => round($existingBalance, 2),
                'spouse_age' => $spouseAge,
            ],
        )];
    }

    /**
     * Modest-earner path (dual_earner mode): fires when the spouse has
     * relevant UK earnings above zero but below twice the Personal Allowance
     * (the "modest-earner heuristic"). The contribution capacity equals their
     * relevant earnings — basic-rate relief at source applies at the rate
     * from TaxConfigService so the uplift is proportional to actual earnings.
     *
     * A spouse earning £8,000 can contribute up to £8,000 gross; at 20%
     * basic-rate relief the net cost is £6,400 and the government uplift is
     * £1,600 — compared with £720 on the flat non-earner £2,880 path.
     */
    private function modestEarnerPath(User $user, mixed $household, float $money): array
    {
        if ($household === null) {
            return [];
        }

        $spouseIncome = (float) ($household->spouse_annual_income ?? 0);
        if ($spouseIncome <= 0) {
            return [];
        }

        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) $incomeTax['personal_allowance'];

        // Modest-earner heuristic: below twice the Personal Allowance (~£25,140)
        // means the spouse is likely a non- or basic-rate taxpayer both now and
        // in retirement, making pension contributions particularly valuable.
        $modestEarnerCeiling = $personalAllowance * 2;
        if ($spouseIncome >= $modestEarnerCeiling) {
            return [];
        }

        $spouseAge = $this->resolveSpouseAge($user);
        if ($spouseAge !== null && $spouseAge >= $this->reliefMaxAge()) {
            return [];
        }

        // Basic-rate relief at source — from TaxConfigService, not hardcoded.
        $basicRate = $this->math->bandRateForBand('basic');
        // Relevant earnings cap the gross contribution; what they already pay
        // in (gross in this mode) has used part of it (B5).
        // Relief is on contributions up to the greater of relevant earnings and
        // the basic amount (FA 2004 s190), so a spouse earning under it can
        // still pay in the basic amount (audit 2026-09-27). Relevant earnings
        // are earnings from work (s189), not total income, which can be
        // pension or rent (CSJ 2026-09-28); unknown earnings count as none.
        $earnings = $household->spouse_annual_earnings !== null ? (float) $household->spouse_annual_earnings : null;
        $reliefLimit = max($earnings ?? 0.0, (float) $this->taxConfig->getPensionAllowances()['relevant_earnings_minimum']);
        $grossCapacity = max(0.0, $reliefLimit - (float) ($household->spouse_pension_input_annual ?? 0));
        // Never more than the household can pay (CSJ 2026-09-30: "affordability
        // check always"): a partner earning £20,000 was told to pay £15,200 in
        // (Save Tax matrix S9). The money pays the net; relief at source adds
        // the basic rate on top (FA 2004 s192).
        $grossCapacity = floor(min($grossCapacity, $basicRate < 1 ? $money / (1 - $basicRate) : 0.0) * 100) / 100;
        if ($grossCapacity < 1) {
            return [];
        }
        $uplift = round($grossCapacity * $basicRate, 2);
        $netCost = round($grossCapacity * (1.0 - $basicRate), 2);

        return [new StrategyRecommendation(
            type: 'non_earner_spouse_pension',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Top up your spouse\'s pension by £%s — instant £%s government top-up',
                number_format((int) $netCost),
                number_format((int) $uplift),
            ),
            description: sprintf(
                '%s, and relief is given on pension contributions up to their relevant UK earnings or the basic amount, whichever is higher. Paying in £%s net gets grossed up to £%s by basic-rate relief at source — that\'s £%s of free government money. They can also draw a separate 25%% tax-free lump sum and use another Personal Allowance in retirement.',
                ($earnings ?? 0.0) > 0
                    ? sprintf('Your spouse earns £%s from work', number_format((int) $earnings))
                    : 'Your spouse has no earnings from work recorded',
                number_format((int) $netCost),
                number_format((int) $grossCapacity),
                number_format((int) $uplift),
            ),
            estimatedAnnualTaxSaved: $uplift,
            extra: [
                'spouse_annual_income' => $spouseIncome,
                'spouse_annual_earnings' => $earnings,
                'gross_capacity' => $grossCapacity,
                'net_cost' => $netCost,
                'government_uplift' => $uplift,
                'basic_rate' => $basicRate,
                'spouse_age' => $spouseAge,
            ],
        )];
    }

    /** No relief on contributions paid after 75: FA 2004 s188(3)(a), from pension.relief_max_age. */
    private function reliefMaxAge(): int
    {
        return (int) $this->taxConfig->getPensionAllowances()['relief_max_age'];
    }

    /**
     * Resolve the spouse's age in whole years, or null when unknown. Looks at
     * family_members (any spouse-class relationship) first, then falls back
     * to a linked spouse user when present on the User model.
     */
    private function resolveSpouseAge(User $user): ?int
    {
        $member = FamilyMember::query()
            ->where('user_id', $user->id)
            ->whereNotNull('date_of_birth')
            ->whereIn('relationship', ['spouse', 'partner', 'wife', 'husband', 'civil_partner'])
            ->first(['date_of_birth']);

        if ($member !== null) {
            return $this->math->ageOf($member->date_of_birth);
        }

        $spouseId = $user->spouse_id ?? null;
        if (! empty($spouseId)) {
            $spouseUser = User::find($spouseId);
            if ($spouseUser instanceof User) {
                return $this->math->ageOf($spouseUser->date_of_birth);
            }
        }

        return null;
    }
}
