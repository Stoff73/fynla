<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use Carbon\Carbon;

/**
 * Evaluates active life events and produces modifiers for downstream pipeline phases.
 *
 * Each event type maps to blocked/prioritised wrappers, liquidity requirements,
 * and affordability overrides that feed into the ContributionWaterfallService.
 */
class LifeEventAssessmentService
{
    /**
     * Assess all life events in the user context and return modifiers.
     *
     * @param  array  $context  User context from UserContextBuilder
     * @return array{
     *     blocked_wrappers: string[],
     *     prioritised_wrappers: string[],
     *     liquidity_priority: string,
     *     affordability_override: bool,
     *     sub_actions: array,
     *     events_assessed: array
     * }
     */
    public function assess(array $context): array
    {
        $lifeEvents = $context['life_events'] ?? [];
        $age = $context['personal']['age'] ?? null;
        $retirementAge = $context['personal']['retirement_age'] ?? null;
        $yearsToRetirement = $context['personal']['years_to_retirement'] ?? null;

        $blockedWrappers = [];
        $prioritisedWrappers = [];
        $liquidityPriority = 'low';
        $affordabilityOverride = false;
        $subActions = [];
        $eventsAssessed = [];

        // Assess each explicit life event
        foreach ($lifeEvents as $event) {
            $eventType = $event['event_type'] ?? '';
            $expectedDate = isset($event['expected_date']) ? Carbon::parse($event['expected_date']) : null;
            $yearsUntil = $expectedDate ? max(0, (int) now()->diffInYears($expectedDate, false)) : null;
            $amount = (float) ($event['amount'] ?? 0);

            $assessment = $this->assessEvent($eventType, $yearsUntil, $amount, $context);

            if ($assessment !== null) {
                $blockedWrappers = array_merge($blockedWrappers, $assessment['blocked_wrappers']);
                $prioritisedWrappers = array_merge($prioritisedWrappers, $assessment['prioritised_wrappers']);

                if ($this->liquidityRank($assessment['liquidity_priority']) > $this->liquidityRank($liquidityPriority)) {
                    $liquidityPriority = $assessment['liquidity_priority'];
                }

                if ($assessment['affordability_override']) {
                    $affordabilityOverride = true;
                }

                $subActions = array_merge($subActions, $assessment['sub_actions']);

                $eventsAssessed[] = [
                    'event' => $event['event_name'] ?? $eventType,
                    'event_type' => $eventType,
                    'years_until' => $yearsUntil,
                    'modifiers' => $assessment,
                ];
            }
        }

        // Assess derived events (not explicit life events but inferred from context)
        $derivedEvents = $this->assessDerivedEvents($context);
        foreach ($derivedEvents as $derived) {
            $blockedWrappers = array_merge($blockedWrappers, $derived['blocked_wrappers']);
            $prioritisedWrappers = array_merge($prioritisedWrappers, $derived['prioritised_wrappers']);

            if ($this->liquidityRank($derived['liquidity_priority']) > $this->liquidityRank($liquidityPriority)) {
                $liquidityPriority = $derived['liquidity_priority'];
            }

            if ($derived['affordability_override']) {
                $affordabilityOverride = true;
            }

            $subActions = array_merge($subActions, $derived['sub_actions']);
            $eventsAssessed[] = $derived;
        }

        return [
            'blocked_wrappers' => array_values(array_unique($blockedWrappers)),
            'prioritised_wrappers' => array_values(array_unique($prioritisedWrappers)),
            'liquidity_priority' => $liquidityPriority,
            'affordability_override' => $affordabilityOverride,
            'sub_actions' => $subActions,
            'events_assessed' => $eventsAssessed,
        ];
    }

    /**
     * Assess an individual event type and return modifiers.
     */
    private function assessEvent(string $eventType, ?int $yearsUntil, float $amount, array $context): ?array
    {
        return match ($eventType) {
            // Income events
            'inheritance' => $this->assessInheritance($yearsUntil, $amount),
            'gift_received' => $this->assessWindfall($yearsUntil, $amount),
            'bonus' => $this->assessWindfall($yearsUntil, $amount),
            'redundancy_payment' => $this->assessRedundancy($yearsUntil, $amount, $context),
            'property_sale' => $this->assessPropertySale($yearsUntil, $amount),
            'business_sale' => $this->assessBusinessSale($yearsUntil, $amount),
            'pension_lump_sum' => $this->assessPensionLumpSum($yearsUntil, $amount, $context),
            'lottery_windfall' => $this->assessWindfall($yearsUntil, $amount),
            'custom_income' => $this->assessWindfall($yearsUntil, $amount),

            // Expense events
            'large_purchase' => $this->assessLargePurchase($yearsUntil, $amount),
            'home_improvement' => $this->assessNearTermExpense($yearsUntil, $amount),
            'wedding' => $this->assessNearTermExpense($yearsUntil, $amount),
            'education_fees' => $this->assessEducationFees($yearsUntil, $amount),
            'gift_given' => $this->assessNearTermExpense($yearsUntil, $amount),
            'medical_expense' => $this->assessMedicalExpense($yearsUntil, $amount, $context),
            'custom_expense' => $this->assessNearTermExpense($yearsUntil, $amount),

            default => null,
        };
    }

    /**
     * Assess derived events inferred from user context.
     */
    private function assessDerivedEvents(array $context): array
    {
        $derived = [];

        // Approaching retirement (within 5 years)
        $yearsToRetirement = $context['personal']['years_to_retirement'] ?? null;
        if ($yearsToRetirement !== null && $yearsToRetirement <= 5) {
            $derived[] = [
                'event' => 'Approaching retirement',
                'event_type' => 'retirement_approaching',
                'years_until' => $yearsToRetirement,
                'blocked_wrappers' => $yearsToRetirement <= 2 ? ['vct', 'eis', 'seis'] : [],
                'prioritised_wrappers' => ['pension', 'isa'],
                'liquidity_priority' => $yearsToRetirement <= 2 ? 'high' : 'medium',
                'affordability_override' => false,
                'sub_actions' => [
                    [
                        'action' => 'Review pension access strategy',
                        'priority' => 'high',
                        'reason' => sprintf('Retirement is %d %s away — consider crystallisation options.', $yearsToRetirement, $yearsToRetirement === 1 ? 'year' : 'years'),
                    ],
                ],
            ];
        }

        // Pension access approaching (within 2 years of age 55/57)
        $age = $context['personal']['age'] ?? null;
        if ($age !== null && $age >= 53 && $age < 57) {
            $derived[] = [
                'event' => 'Pension access approaching',
                'event_type' => 'pension_access_approaching',
                'years_until' => max(0, 55 - $age),
                'blocked_wrappers' => [],
                'prioritised_wrappers' => ['pension'],
                'liquidity_priority' => 'low',
                'affordability_override' => false,
                'sub_actions' => [
                    [
                        'action' => 'Review pension access options',
                        'priority' => 'medium',
                        'reason' => 'You are approaching the pension access age — review drawdown, annuity, and lump sum options.',
                    ],
                ],
            ];
        }

        return $derived;
    }

    // ──────────────────────────────────────────────
    // Event-specific assessments
    // ──────────────────────────────────────────────

    private function assessInheritance(?int $yearsUntil, float $amount): array
    {
        return [
            'blocked_wrappers' => [],
            'prioritised_wrappers' => $amount > 50000 ? ['isa', 'pension'] : ['isa'],
            'liquidity_priority' => 'low',
            'affordability_override' => false,
            'sub_actions' => $amount > 50000 ? [
                [
                    'action' => 'Consider Inheritance Tax planning for received assets',
                    'priority' => 'medium',
                    'reason' => sprintf('Expected inheritance of %s may benefit from early tax-efficient deployment.', number_format($amount, 0, '.', ',')),
                ],
            ] : [],
        ];
    }

    private function assessRedundancy(?int $yearsUntil, float $amount, array $context): array
    {
        return [
            'blocked_wrappers' => ['offshore_bond', 'onshore_bond', 'vct', 'eis', 'seis'],
            'prioritised_wrappers' => ['isa', 'premium_bonds'],
            'liquidity_priority' => 'high',
            'affordability_override' => true,
            'sub_actions' => [
                [
                    'action' => 'Review emergency fund adequacy',
                    'priority' => 'high',
                    'reason' => 'Redundancy risk requires higher liquidity reserves.',
                ],
                [
                    'action' => 'Check income protection cover',
                    'priority' => 'high',
                    'reason' => 'Ensure income protection is in place before redundancy.',
                ],
            ],
        ];
    }

    private function assessPropertySale(?int $yearsUntil, float $amount): array
    {
        return [
            'blocked_wrappers' => [],
            'prioritised_wrappers' => $amount > 100000 ? ['isa', 'pension', 'premium_bonds'] : ['isa'],
            'liquidity_priority' => $yearsUntil !== null && $yearsUntil <= 1 ? 'medium' : 'low',
            'affordability_override' => false,
            'sub_actions' => [
                [
                    'action' => 'Plan Capital Gains Tax position for property sale proceeds',
                    'priority' => 'medium',
                    'reason' => 'Property sale may generate Capital Gains Tax liability — plan reinvestment timing.',
                ],
            ],
        ];
    }

    private function assessBusinessSale(?int $yearsUntil, float $amount): array
    {
        return [
            'blocked_wrappers' => [],
            'prioritised_wrappers' => ['pension', 'isa', 'eis'],
            'liquidity_priority' => 'medium',
            'affordability_override' => false,
            'sub_actions' => [
                [
                    'action' => 'Review Business Asset Disposal Relief eligibility',
                    'priority' => 'high',
                    'reason' => 'Business sale may qualify for reduced Capital Gains Tax rate — take specialist advice.',
                ],
                [
                    'action' => 'Consider Enterprise Investment Scheme reinvestment',
                    'priority' => 'medium',
                    'reason' => 'Enterprise Investment Scheme deferral relief may apply to Capital Gains Tax from the sale.',
                ],
            ],
        ];
    }

    private function assessPensionLumpSum(?int $yearsUntil, float $amount, array $context): array
    {
        return [
            'blocked_wrappers' => [],
            'prioritised_wrappers' => ['isa', 'premium_bonds'],
            'liquidity_priority' => 'low',
            'affordability_override' => false,
            'sub_actions' => [
                [
                    'action' => 'Check Money Purchase Annual Allowance implications',
                    'priority' => 'high',
                    'reason' => 'Accessing pension flexibly triggers Money Purchase Annual Allowance — future pension contributions limited to the reduced allowance.',
                ],
            ],
        ];
    }

    private function assessWindfall(?int $yearsUntil, float $amount): array
    {
        return [
            'blocked_wrappers' => [],
            'prioritised_wrappers' => $amount > 20000 ? ['isa', 'pension'] : ['isa'],
            'liquidity_priority' => 'low',
            'affordability_override' => false,
            'sub_actions' => [],
        ];
    }

    private function assessLargePurchase(?int $yearsUntil, float $amount): array
    {
        $isNearTerm = $yearsUntil !== null && $yearsUntil <= 3;

        return [
            'blocked_wrappers' => $isNearTerm ? ['pension', 'vct', 'eis', 'seis', 'offshore_bond', 'onshore_bond'] : [],
            'prioritised_wrappers' => $isNearTerm ? ['cash_isa', 'premium_bonds'] : [],
            'liquidity_priority' => $isNearTerm ? 'high' : 'low',
            'affordability_override' => $isNearTerm && $amount > 10000,
            'sub_actions' => $isNearTerm ? [
                [
                    'action' => 'Earmark funds for upcoming purchase',
                    'priority' => 'high',
                    'reason' => sprintf('Large purchase of %s expected within %d %s — keep funds liquid.', number_format($amount, 0, '.', ','), $yearsUntil, $yearsUntil === 1 ? 'year' : 'years'),
                ],
            ] : [],
        ];
    }

    private function assessNearTermExpense(?int $yearsUntil, float $amount): array
    {
        $isNearTerm = $yearsUntil !== null && $yearsUntil <= 2;

        return [
            'blocked_wrappers' => $isNearTerm ? ['pension', 'vct', 'eis', 'seis'] : [],
            'prioritised_wrappers' => $isNearTerm ? ['cash_isa'] : [],
            'liquidity_priority' => $isNearTerm ? 'medium' : 'low',
            'affordability_override' => $isNearTerm && $amount > 5000,
            'sub_actions' => [],
        ];
    }

    private function assessEducationFees(?int $yearsUntil, float $amount): array
    {
        $isNearTerm = $yearsUntil !== null && $yearsUntil <= 3;

        return [
            'blocked_wrappers' => $isNearTerm ? ['pension', 'vct', 'eis', 'seis', 'offshore_bond', 'onshore_bond'] : [],
            'prioritised_wrappers' => $isNearTerm ? ['cash_isa', 'premium_bonds'] : ['isa'],
            'liquidity_priority' => $isNearTerm ? 'high' : 'medium',
            'affordability_override' => $isNearTerm,
            'sub_actions' => $amount > 50000 ? [
                [
                    'action' => 'Review education funding strategy',
                    'priority' => 'medium',
                    'reason' => 'Significant education costs ahead — consider Junior ISA or dedicated savings vehicle.',
                ],
            ] : [],
        ];
    }

    private function assessMedicalExpense(?int $yearsUntil, float $amount, array $context): array
    {
        return [
            'blocked_wrappers' => ['vct', 'eis', 'seis'],
            'prioritised_wrappers' => ['cash_isa', 'premium_bonds'],
            'liquidity_priority' => 'high',
            'affordability_override' => true,
            'sub_actions' => [
                [
                    'action' => 'Check critical illness cover eligibility',
                    'priority' => 'high',
                    'reason' => 'Medical expense expected — review whether existing critical illness cover applies.',
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Convert liquidity priority to numeric rank for comparison.
     */
    private function liquidityRank(string $priority): int
    {
        return match ($priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
