<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\FamilyMember;
use App\Models\User;
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

        if ($context->mode === 'single_earner_couple') {
            return $this->nonEarnerPath($user, $household);
        }

        if ($context->mode === 'dual_earner') {
            return $this->modestEarnerPath($user, $household);
        }

        return [];
    }

    /**
     * Original non-earner path (single_earner_couple mode): flat £2,880 net
     * net → gross via basic-rate relief at source (TaxStrategyMath::nonEarnerPensionContribution).
     */
    private function nonEarnerPath(User $user, mixed $household): array
    {
        $spouseAge = $this->resolveSpouseAge($user);
        if ($spouseAge !== null && $spouseAge >= $this->reliefMaxAge()) {
            return [];
        }

        $figures = $this->math->nonEarnerPensionContribution();
        // What the spouse already pays in (stored net for a non-earner, the
        // relief-at-source shape the capture writes) comes off the top (B5).
        $alreadyPaid = (float) ($household?->spouse_pension_input_annual ?? 0);
        $netContribution = round(max(0.0, $figures['net'] - $alreadyPaid), 2);
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
    private function modestEarnerPath(User $user, mixed $household): array
    {
        if ($household === null) {
            return [];
        }

        $spouseIncome = (float) ($household->spouse_annual_income ?? 0);
        if ($spouseIncome <= 0) {
            return [];
        }

        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? 12570);

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
        $grossCapacity = max(0.0, $spouseIncome - (float) ($household->spouse_pension_input_annual ?? 0));
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
                'Your spouse earns £%s, which counts as relevant UK earnings for pension purposes. Paying in £%s net gets grossed up to £%s by basic-rate relief at source — that\'s £%s of free government money. They can also draw a separate 25%% tax-free lump sum and use another Personal Allowance in retirement.',
                number_format((int) $spouseIncome),
                number_format((int) $netCost),
                number_format((int) $grossCapacity),
                number_format((int) $uplift),
            ),
            estimatedAnnualTaxSaved: $uplift,
            extra: [
                'spouse_annual_income' => $spouseIncome,
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
