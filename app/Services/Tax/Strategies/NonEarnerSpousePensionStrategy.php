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
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        if ($context->mode !== 'single_earner_couple') {
            return [];
        }

        $user = $context->user;
        $household = $context->household;

        $spouseAge = $this->resolveSpouseAge($user);
        if ($spouseAge !== null && $spouseAge >= 75) {
            return [];
        }

        $netContribution = 2880.0;
        $governmentUplift = 720.0;
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
