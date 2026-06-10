<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\Constants\TaxDefaults;
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
 * basic-rate uplift = £720/yr direct saving. Spouse age is resolved from
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
     * → £3,600 gross → £720 government uplift per TaxDefaults constants.
     */
    private function nonEarnerPath(User $user, mixed $household): array
    {
        $spouseAge = $this->resolveSpouseAge($user);
        if ($spouseAge !== null && $spouseAge >= 75) {
            return [];
        }

        // M9 — sourced from TaxDefaults; promote to TaxConfigService once
        // the schema gains a non_earner_pension key (CSJTODO S-3).
        $netContribution = (float) TaxDefaults::NON_EARNER_PENSION_NET_CONTRIBUTION;
        $governmentUplift = (float) TaxDefaults::NON_EARNER_PENSION_GOVERNMENT_UPLIFT;
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
        if ($spouseAge !== null && $spouseAge >= 75) {
            return [];
        }

        // Basic-rate relief at source — from TaxConfigService, not hardcoded.
        $basicRate = $this->math->bandRateForBand('basic');
        $grossCapacity = $spouseIncome; // relevant UK earnings = contribution cap
        $uplift = round($grossCapacity * $basicRate, 2);
        $netCost = round($grossCapacity * (1.0 - $basicRate), 2);

        return [new StrategyRecommendation(
            type: 'non_earner_spouse_pension',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Max out your spouse\'s pension on their £%s earnings — instant £%s government top-up',
                number_format((int) $spouseIncome),
                number_format((int) $uplift),
            ),
            description: sprintf(
                'Your spouse earns £%s, which counts as relevant UK earnings for pension purposes. Contributing the full £%s gets grossed up to £%s by basic-rate relief at source — that\'s £%s of free government money. They can also draw a separate 25%% tax-free lump sum and use another Personal Allowance in retirement.',
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
