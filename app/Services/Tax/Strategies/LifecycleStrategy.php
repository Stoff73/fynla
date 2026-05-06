<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\Constants\TaxDefaults;
use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\FamilyMember;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategies #16 (Lifetime ISA, under-40s) + #17 (Junior ISA) + #18 (Junior Pension).
 */
final class LifecycleStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $recommendations = [];
        $isa = $this->taxConfig->getISAAllowances();

        // #16 — Lifetime ISA (under-40s)
        $userAge = $this->math->ageOf($user->date_of_birth);
        $lisa = $isa['lifetime_isa'] ?? [];
        $lisaMaxAgeToOpen = (int) ($lisa['max_age_to_open'] ?? 39);
        $lisaAnnual = (float) ($lisa['annual_allowance'] ?? 4000);
        $lisaBonusRate = (float) ($lisa['government_bonus_rate'] ?? 0.25);

        if ($userAge !== null && $userAge >= 18 && $userAge <= $lisaMaxAgeToOpen) {
            $isaAllowance = (float) ($isa['annual_allowance'] ?? 20000);
            $isaUsed = $this->math->estimateIsaSubscriptionsThisYear($user);
            $isaRemaining = max(0, $isaAllowance - $isaUsed);
            $contribution = min($lisaAnnual, $isaRemaining);

            if ($contribution > 0) {
                $bonus = $contribution * $lisaBonusRate;
                $recommendations[] = new StrategyRecommendation(
                    type: 'lifetime_isa',
                    category: StrategyCategory::Lifecycle,
                    priority: StrategyPriority::Medium,
                    title: sprintf('Open a Lifetime ISA for a £%s government bonus every year', number_format((int) $bonus)),
                    description: sprintf(
                        'You\'re under %d. Contributing £%s a year to a Lifetime ISA unlocks a £%s government top-up — usable for a first home (up to £450,000) or from age 60. The contribution counts toward your £%s overall ISA allowance.',
                        $lisaMaxAgeToOpen + 1,
                        number_format((int) $contribution),
                        number_format((int) $bonus),
                        number_format((int) $isaAllowance),
                    ),
                    estimatedAnnualTaxSaved: round($bonus, 2),
                    extra: [
                        'suggested_contribution' => round($contribution, 2),
                        'government_bonus' => round($bonus, 2),
                        'user_age' => $userAge,
                    ],
                );
            }
        }

        // #17 + #18 — count dependant children under 18
        $juniorIsa = $isa['junior_isa'] ?? [];
        $juniorIsaMaxAge = (int) ($juniorIsa['max_age'] ?? 17);
        $juniorIsaAnnual = (float) ($juniorIsa['annual_allowance'] ?? 9000);

        $children = FamilyMember::query()
            ->where('user_id', $user->id)
            ->whereNotNull('date_of_birth')
            ->whereIn('relationship', ['child', 'son', 'daughter'])
            ->get(['date_of_birth']);

        $childrenUnder18 = $children->filter(function ($child) use ($juniorIsaMaxAge) {
            $age = $this->math->ageOf($child->date_of_birth);

            return $age !== null && $age <= $juniorIsaMaxAge;
        });
        $childCount = $childrenUnder18->count();

        if ($childCount > 0) {
            $totalJisaCapacity = $childCount * $juniorIsaAnnual;
            $recommendations[] = new StrategyRecommendation(
                type: 'junior_isa',
                category: StrategyCategory::Lifecycle,
                priority: StrategyPriority::Medium,
                title: sprintf(
                    '%s — up to £%s of Junior ISA capacity a year',
                    $childCount === 1
                        ? 'You have 1 child under 18 in your household'
                        : sprintf('You have %d children under 18 in your household', $childCount),
                    number_format((int) $totalJisaCapacity),
                ),
                description: sprintf(
                    'Each child under 18 has a £%s annual Junior ISA allowance — separate from your own £%s. All interest, dividends and capital gains inside the wrapper are tax-free until they turn 18.',
                    number_format((int) $juniorIsaAnnual),
                    number_format((int) ($isa['annual_allowance'] ?? 20000)),
                ),
                estimatedAnnualTaxSaved: null,
                extra: [
                    'children_under_18' => $childCount,
                    'total_jisa_capacity' => $totalJisaCapacity,
                ],
            );

            // #18 — Junior Pension. £2,880 net per child grossed up to £3,600
            // by HMRC (£720 = 20% basic-rate relief grossed onto an £2,880 net
            // contribution; HMRC pension input cap for non-earners). Sourced
            // from TaxDefaults so every strategy that quotes the figure
            // updates together; CSJTODO S-3 promotes this to TaxConfigService
            // once the schema has a non_earner_pension key.
            $juniorPensionNet = (float) TaxDefaults::NON_EARNER_PENSION_NET_CONTRIBUTION;
            $juniorPensionUplift = (float) TaxDefaults::NON_EARNER_PENSION_GOVERNMENT_UPLIFT;
            $totalUplift = $childCount * $juniorPensionUplift;
            $recommendations[] = new StrategyRecommendation(
                type: 'junior_pension',
                category: StrategyCategory::Lifecycle,
                priority: StrategyPriority::Medium,
                title: sprintf(
                    'Open a pension for each child — instant £%s a year of free money',
                    number_format((int) $totalUplift),
                ),
                description: sprintf(
                    'Anyone, including a child with no income, can hold a personal pension. £%s contributed per child is topped up to £%s by the government — that\'s £%s of free money per child, every year. Decades of compounding sheltered from tax.',
                    number_format((int) $juniorPensionNet),
                    number_format((int) ($juniorPensionNet + $juniorPensionUplift)),
                    number_format((int) $juniorPensionUplift),
                ),
                estimatedAnnualTaxSaved: round($totalUplift, 2),
                extra: [
                    'children_under_18' => $childCount,
                    'net_contribution_per_child' => $juniorPensionNet,
                    'gross_contribution_per_child' => $juniorPensionNet + $juniorPensionUplift,
                    'total_government_uplift' => $totalUplift,
                ],
            );
        }

        return $recommendations;
    }
}
