<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\User;
use App\Services\Goals\LifeEventService;
use App\Services\TaxConfigService;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;

class GiftingStrategyOptimizer
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly FutureValueCalculator $fvCalculator,
        private readonly TaxConfigService $taxConfig,
        private readonly LifeEventService $lifeEventService
    ) {}

    /**
     * Calculate optimal gifting strategy to reduce IHT liability to zero or minimum
     *
     * Prioritizes PETs (every 7 years), uses annual exemptions, and CLTs as last resort
     *
     * @param  float  $projectedEstateValue  Estate value at expected death
     * @param  float  $currentIHTLiability  Current projected IHT liability
     * @param  int  $yearsUntilDeath  Years until expected death
     * @param  User  $user  User for income/expenditure check
     * @param  float  $totalNRBAvailable  Total NRB available (including spouse transfer)
     * @param  float  $rnrbAvailable  RNRB available
     * @param  float  $annualExpenditure  Annual expenditure for gifting from income calculation
     * @return array Gifting strategy recommendations
     */
    public function calculateOptimalGiftingStrategy(
        float $projectedEstateValue,
        float $currentIHTLiability,
        int $yearsUntilDeath,
        User $user,
        float $totalNRBAvailable,
        float $rnrbAvailable,
        float $annualExpenditure = 0
    ): array {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $giftingConfig = $this->taxConfig->getGiftingExemptions();
        $ihtRate = $ihtConfig['standard_rate']; // 0.40
        $annualExemption = $giftingConfig['annual_exemption']; // £3,000

        $strategies = [];
        $remainingIHTLiability = $currentIHTLiability;
        $remainingEstateValue = $projectedEstateValue;

        // 1. Annual Exemption Strategy (£3,000/year)
        $annualExemptionStrategy = $this->calculateAnnualExemptionStrategy(
            $yearsUntilDeath,
            $annualExemption,
            $ihtRate
        );

        $strategies[] = $annualExemptionStrategy;
        $remainingIHTLiability -= $annualExemptionStrategy['iht_saved'];
        $remainingEstateValue -= $annualExemptionStrategy['total_gifted'];

        // 1b. Planned Gift Life Events (gift_given events reduce available capacity)
        $plannedGiftsStrategy = $this->calculatePlannedGiftEventsImpact(
            $user,
            $yearsUntilDeath,
            $ihtRate
        );

        if ($plannedGiftsStrategy['total_gifted'] > 0) {
            $strategies[] = $plannedGiftsStrategy;
            $remainingIHTLiability -= $plannedGiftsStrategy['iht_saved'];
            $remainingEstateValue -= $plannedGiftsStrategy['total_gifted'];
        }

        // 2. Gifting from Income Strategy (if user has income and expenditure data)
        if ($user->annual_employment_income || $user->annual_self_employment_income) {
            $totalIncome = ($user->annual_employment_income ?? 0) +
                          ($user->annual_self_employment_income ?? 0) +
                          ($user->annual_rental_income ?? 0) +
                          ($user->annual_dividend_income ?? 0) +
                          ($user->annual_other_income ?? 0);

            if ($totalIncome > 0 && $annualExpenditure > 0) {
                $giftingFromIncomeStrategy = $this->calculateGiftingFromIncomeStrategy(
                    $totalIncome,
                    $annualExpenditure,
                    $yearsUntilDeath,
                    $ihtRate
                );

                if ($giftingFromIncomeStrategy['can_afford']) {
                    $strategies[] = $giftingFromIncomeStrategy;
                    $remainingIHTLiability -= $giftingFromIncomeStrategy['iht_saved'];
                    $remainingEstateValue -= $giftingFromIncomeStrategy['total_gifted'];
                }
            }
        }

        // 3. PET Strategy (7-year cycle)
        if ($remainingIHTLiability > 0 && $yearsUntilDeath >= 7) {
            $petStrategy = $this->calculatePETStrategy(
                $remainingEstateValue,
                $remainingIHTLiability,
                $yearsUntilDeath,
                $totalNRBAvailable,
                $ihtRate
            );

            $strategies[] = $petStrategy;
            $remainingIHTLiability -= $petStrategy['iht_saved'];
            $remainingEstateValue -= $petStrategy['total_gifted'];
        }

        // 4. CLT Strategy (last resort if still have IHT liability)
        if ($remainingIHTLiability > 0) {
            $cltStrategy = $this->calculateCLTStrategy(
                $remainingIHTLiability,
                $ihtRate
            );

            $strategies[] = $cltStrategy;
            $remainingIHTLiability -= $cltStrategy['iht_saved'];
        }

        // Calculate total impact
        $totalIHTSaved = array_sum(array_column($strategies, 'iht_saved'));
        $totalGifted = array_sum(array_column($strategies, 'total_gifted'));

        return [
            'strategies' => $strategies,
            'summary' => [
                'original_iht_liability' => round($currentIHTLiability, 2),
                'total_iht_saved' => round($totalIHTSaved, 2),
                'remaining_iht_liability' => round(max(0, $currentIHTLiability - $totalIHTSaved), 2),
                'total_gifted' => round($totalGifted, 2),
                'projected_estate_after_gifting' => round($projectedEstateValue - $totalGifted, 2),
                'reduction_percentage' => $currentIHTLiability > 0 ?
                    round(($totalIHTSaved / $currentIHTLiability) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Calculate annual exemption strategy (£3,000/year)
     */
    private function calculateAnnualExemptionStrategy(
        int $yearsUntilDeath,
        float $annualExemption,
        float $ihtRate
    ): array {
        $totalGifted = $annualExemption * $yearsUntilDeath;
        $ihtSaved = $totalGifted * $ihtRate;

        return [
            'strategy_name' => 'Annual Exemption',
            'priority' => 1,
            'description' => "Gift £{$annualExemption} per year using annual exemption (immediately exempt)",
            'total_gifted' => round($totalGifted, 2),
            'annual_amount' => round($annualExemption, 2),
            'years' => $yearsUntilDeath,
            'iht_saved' => round($ihtSaved, 2),
            'risk_level' => 'Low',
            'exempt_immediately' => true,
            'implementation_steps' => [
                "Set up standing order for £{$annualExemption} per year to beneficiaries",
                'Consider gifting to multiple beneficiaries (no limit on number)',
                'Can carry forward unused exemption from previous year',
                'Keep records of all gifts for IHT400 form',
            ],
        ];
    }

    /**
     * Calculate gifting from income strategy (unlimited if regular and affordable)
     */
    private function calculateGiftingFromIncomeStrategy(
        float $totalIncome,
        float $annualExpenditure,
        int $yearsUntilDeath,
        float $ihtRate
    ): array {
        // Conservative approach: only gift 50% of surplus income to maintain standard of living
        $surplusIncome = max(0, $totalIncome - $annualExpenditure);
        $safeGiftingAmount = $surplusIncome * 0.5;

        $totalGifted = $safeGiftingAmount * $yearsUntilDeath;
        $ihtSaved = $totalGifted * $ihtRate;

        $canAfford = $surplusIncome > 0 && $safeGiftingAmount >= 1000; // Minimum £1,000/year to be worthwhile

        return [
            'strategy_name' => 'Normal Expenditure Out of Income',
            'priority' => 2,
            'description' => 'Make regular gifts from surplus income (unlimited and immediately exempt)',
            'can_afford' => $canAfford,
            'total_income' => round($totalIncome, 2),
            'annual_expenditure' => round($annualExpenditure, 2),
            'surplus_income' => round($surplusIncome, 2),
            'annual_amount' => round($safeGiftingAmount, 2),
            'total_gifted' => round($totalGifted, 2),
            'years' => $yearsUntilDeath,
            'iht_saved' => round($ihtSaved, 2),
            'risk_level' => 'Low',
            'exempt_immediately' => true,
            'implementation_steps' => [
                'Set up regular standing orders to beneficiaries',
                'Must be made from income, not capital',
                'Must not affect your standard of living',
                'Keep detailed records showing regularity (3+ years of pattern)',
                'Document income sources and expenditure',
            ],
            'notes' => $canAfford ?
                'You can afford to gift £'.number_format($safeGiftingAmount, 0).' per year from surplus income' :
                'Insufficient surplus income for this strategy (need income and expenditure data)',
        ];
    }

    /**
     * Calculate PET strategy (7-year cycle)
     */
    private function calculatePETStrategy(
        float $remainingEstateValue,
        float $remainingIHTLiability,
        int $yearsUntilDeath,
        float $totalNRBAvailable,
        float $ihtRate
    ): array {
        // Calculate how many complete 7-year cycles we have
        $complete7YearCycles = floor($yearsUntilDeath / 7);

        // CRITICAL: PETs should NEVER exceed NRB to avoid immediate IHT charge
        // Gift up to NRB per cycle - this is the maximum tax-efficient amount
        $amountPerCycle = $totalNRBAvailable; // Always gift NRB amount per cycle

        // Cap at remaining estate value if necessary
        $amountPerCycle = min($amountPerCycle, $remainingEstateValue);

        $totalGifted = $amountPerCycle * $complete7YearCycles;
        $ihtSaved = $totalGifted * $ihtRate;

        $giftSchedule = [];
        for ($i = 0; $i < $complete7YearCycles; $i++) {
            $giftSchedule[] = [
                'year' => ($i * 7),
                'amount' => round($amountPerCycle, 2),
                'becomes_exempt' => ($i * 7) + 7,
                'iht_reduction' => round($amountPerCycle * $ihtRate, 2),
            ];
        }

        return [
            'strategy_name' => 'Potentially Exempt Transfers (PETs)',
            'priority' => 3,
            'description' => 'Make larger gifts that become exempt after 7 years',
            'number_of_cycles' => $complete7YearCycles,
            'amount_per_cycle' => round($amountPerCycle, 2),
            'total_gifted' => round($totalGifted, 2),
            'gift_schedule' => $giftSchedule,
            'years_until_death' => $yearsUntilDeath,
            'iht_saved' => round($ihtSaved, 2),
            'risk_level' => 'Medium',
            'exempt_immediately' => false,
            'taper_relief_from_year' => 3,
            'implementation_steps' => [
                'Gift £'.number_format($amountPerCycle, 0).' every 7 years to maximize IHT efficiency',
                'Consider gifting to discretionary trust for flexibility',
                'Gifts must not have reservation of benefit',
                'Keep detailed gift records with dates and amounts',
                'Taper relief applies if you survive 3-7 years',
            ],
            'notes' => $complete7YearCycles > 0 ?
                "You have {$complete7YearCycles} complete 7-year cycle(s) before expected death" :
                'Insufficient time for PET strategy (need at least 7 years)',
        ];
    }

    /**
     * Calculate CLT strategy (into discretionary trust - last resort)
     */
    private function calculateCLTStrategy(
        float $remainingIHTLiability,
        float $ihtRate
    ): array {
        // CLT attracts immediate lifetime rate charge, then potentially full rate on death within 7 years
        // Amount needed to reduce estate to eliminate remaining IHT
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $cltRate = (float) ($ihtConfig['chargeable_lifetime_transfers']['lifetime_rate'] ?? 0.20);
        $targetGiftAmount = $remainingIHTLiability / $ihtRate;

        // CLT immediate charge
        $immediateCLTCharge = $targetGiftAmount * $cltRate;

        // Net benefit: full IHT rate saved - CLT charge paid now
        $netIHTSaved = ($targetGiftAmount * $ihtRate) - $immediateCLTCharge;

        return [
            'strategy_name' => 'Chargeable Lifetime Transfer (CLT) into Trust',
            'priority' => 4,
            'description' => 'Transfer assets into discretionary trust (attracts immediate '.round($cltRate * 100).'% charge)',
            'gift_amount' => round($targetGiftAmount, 2),
            'immediate_clt_charge' => round($immediateCLTCharge, 2),
            'clt_rate' => $cltRate,
            'total_gifted' => round($targetGiftAmount, 2),
            'iht_saved' => round($netIHTSaved, 2),
            'risk_level' => 'Medium-High',
            'exempt_immediately' => false,
            'implementation_steps' => [
                'Set up discretionary trust with professional trustees',
                'Transfer £'.number_format($targetGiftAmount, 0).' into trust',
                'Pay immediate '.round($cltRate * 100).'% Inheritance Tax charge on transfer',
                'Trust becomes exempt after 7 years',
                'Consider 10-year anniversary charges (6% every 10 years)',
                'Maintain trust administration and file IHT100 returns',
            ],
            'notes' => 'CLT is a last resort strategy. The immediate '.round($cltRate * 100).'% charge must be paid upfront, but it removes growth from your estate and becomes fully exempt after 7 years.',
            'cost_benefit_analysis' => [
                'immediate_cost' => round($immediateCLTCharge, 2),
                'future_iht_saved' => round($targetGiftAmount * $ihtRate, 2),
                'net_saving' => round($netIHTSaved, 2),
            ],
        ];
    }

    /**
     * Calculate IHT impact of planned gift_given life events.
     *
     * Finds future gift_given and gift_received events and factors them into
     * the gifting strategy. Gift events that fall within the 7-year window
     * are PETs; those beyond 7 years are fully exempt.
     */
    private function calculatePlannedGiftEventsImpact(
        User $user,
        int $yearsUntilDeath,
        float $ihtRate
    ): array {
        $events = $this->lifeEventService->getActiveEventsForProjection($user->id);

        $certaintyWeights = [
            'confirmed' => 1.0,
            'likely' => 0.75,
            'possible' => 0.5,
            'speculative' => 0.25,
        ];

        $totalGifted = 0;
        $giftEvents = [];

        foreach ($events as $event) {
            if ($event->event_type !== 'gift_given') {
                continue;
            }

            $yearsUntilEvent = max(0, (float) $event->years_until_event);
            $weight = $certaintyWeights[$event->certainty] ?? 0.5;
            $weightedAmount = (float) $event->amount * $weight;
            $isExemptAfter7Years = $yearsUntilEvent + 7 <= $yearsUntilDeath;

            $totalGifted += $weightedAmount;
            $giftEvents[] = [
                'event_name' => $event->event_name,
                'amount' => (float) $event->amount,
                'weighted_amount' => round($weightedAmount, 2),
                'certainty' => $event->certainty,
                'expected_date' => $event->expected_date->toDateString(),
                'years_until_event' => round($yearsUntilEvent, 1),
                'will_be_exempt' => $isExemptAfter7Years,
            ];
        }

        // IHT saved: gifts that will be fully exempt (made 7+ years before death)
        $exemptTotal = collect($giftEvents)
            ->where('will_be_exempt', true)
            ->sum('weighted_amount');

        $ihtSaved = $exemptTotal * $ihtRate;

        return [
            'strategy_name' => 'Planned Gift Life Events',
            'priority' => 1,
            'description' => 'Planned gifts from your life events that will reduce your taxable estate',
            'total_gifted' => round($totalGifted, 2),
            'iht_saved' => round($ihtSaved, 2),
            'gift_events' => $giftEvents,
            'risk_level' => 'Low',
            'exempt_immediately' => false,
            'notes' => count($giftEvents) > 0
                ? count($giftEvents).' planned gift'.($giftEvents !== 1 ? 's' : '').' totalling £'.number_format($totalGifted, 0).' (certainty-weighted)'
                : 'No planned gift events found',
        ];
    }
}
