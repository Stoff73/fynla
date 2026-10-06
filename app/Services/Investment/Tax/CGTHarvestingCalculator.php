<?php

declare(strict_types=1);

namespace App\Services\Investment\Tax;

use App\Models\Investment\Holding;
use App\Models\User;
use App\Services\Tax\ChargeableGains;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Capital Gains Tax Loss Harvesting Calculator
 * Identifies opportunities to realize losses to offset gains
 * Uses active tax year rates from TaxConfigService
 *
 * UK CGT Rules:
 * - Annual exemption varies by tax year
 * - CGT rates vary by tax year and income level
 * - Can carry forward losses indefinitely
 * - 30-day bed and breakfasting rule
 * - Same-day and 30-day rule for share identification
 */
class CGTHarvestingCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly ChargeableGains $chargeableGains,
        private readonly TaxStrategyMath $math,
    ) {}

    /**
     * Calculate tax-loss harvesting opportunities
     *
     * @param  int  $userId  User ID
     * @param  array  $options  Options (cgt_allowance, expected_gains, tax_rate)
     * @return array Tax-loss harvesting analysis
     */
    public function calculateHarvestingOpportunities(int $userId, array $options = []): array
    {
        // Get CGT allowance from config
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();

        $cgtAllowance = $options['cgt_allowance'] ?? $cgtConfig['annual_exempt_amount'];
        $expectedGains = $options['expected_gains'] ?? 0;
        $user = User::find($userId);
        $taxRate = $options['tax_rate'] ?? $this->rateFor($user, $cgtConfig);
        $lossCarryforward = $options['loss_carryforward'] ?? 0;

        // Holdings with losses in chargeable accounts, at the user's share
        $holdings = $user ? $this->getHoldingsWithLosses($user) : collect();

        if ($holdings->isEmpty()) {
            return [
                'success' => true,
                'opportunities' => [],
                'message' => 'No unrealised losses to use against gains',
                'total_harvestable_losses' => 0,
            ];
        }

        // Analyze each holding
        $opportunities = $this->analyzeHoldings($holdings, $cgtAllowance, $expectedGains, $taxRate);

        // Calculate optimal harvesting strategy
        $harvestingStrategy = $this->calculateOptimalStrategy(
            $opportunities,
            $expectedGains,
            $cgtAllowance,
            $lossCarryforward,
            $taxRate
        );

        // Generate recommendations
        $recommendations = $this->generateRecommendations($harvestingStrategy, $expectedGains);

        return [
            'success' => true,
            'cgt_allowance' => $cgtAllowance,
            'expected_gains' => $expectedGains,
            'loss_carryforward' => $lossCarryforward,
            'opportunities' => $opportunities,
            'harvesting_strategy' => $harvestingStrategy,
            'recommendations' => $recommendations,
            'total_harvestable_losses' => array_sum(array_column($opportunities, 'loss_amount')),
            'potential_tax_saving' => $harvestingStrategy['total_tax_saving'],
        ];
    }

    /**
     * Get holdings with unrealized losses
     *
     * @param  int  $userId  User ID
     * @return Collection Holdings with losses
     */
    private function getHoldingsWithLosses(User $user): Collection
    {
        // The one home for chargeable gains (item 8): not ISAs, Venture Capital
        // Trusts, pensions or investment bonds, and a joint account at the
        // user's share (Rule 6).
        return $this->chargeableGains->holdingsFor($user)
            ->filter(fn (array $row) => $row['gain'] < 0)
            ->values();
    }

    /**
     * The CGT rate on the next pound of gain for this user: the lower rate only
     * within the unused basic rate band (TCGA 1992 s1H,
     * TaxStrategyMath::capitalGainsTaxOn), as the Tax plan's Bed & ISA uses.
     */
    private function rateFor(?User $user, array $cgtConfig): float
    {
        return $user ? $this->math->capitalGainsTaxOn($user, 1.0)['marginal_rate'] : (float) $cgtConfig['higher_rate'];
    }

    /**
     * Analyze holdings for harvesting potential
     *
     * @param  Collection  $holdings  Holdings with losses
     * @param  float  $cgtAllowance  CGT annual allowance
     * @param  float  $expectedGains  Expected realized gains
     * @param  float  $taxRate  CGT tax rate
     * @return array Harvesting opportunities
     */
    private function analyzeHoldings(
        Collection $holdings,
        float $cgtAllowance,
        float $expectedGains,
        float $taxRate
    ): array {
        $opportunities = [];

        foreach ($holdings as $row) {
            $holding = $row['holding'];
            $loss = abs($row['gain']);
            $lossPercent = (($loss / $row['cost']) * 100);

            // Calculate holding period
            $holdingPeriod = $this->calculateHoldingPeriod($holding);

            // Tax saving if loss is realized
            $taxSaving = min($loss, $expectedGains) * $taxRate;

            // Recovery potential analysis
            $recoveryAnalysis = $this->analyzeRecoveryPotential($holding, $lossPercent);

            $opportunities[] = [
                'holding_id' => $holding->id,
                'account_id' => $row['account_id'],
                'security_name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'isin' => $holding->isin,
                'cost_basis' => round($row['cost'], 2),
                'current_value' => round($row['value'], 2),
                'loss_amount' => round($loss, 2),
                'loss_percent' => round($lossPercent, 2),
                'holding_period_days' => $holdingPeriod,
                'potential_tax_saving' => round($taxSaving, 2),
                'recovery_potential' => $recoveryAnalysis,
                'priority' => $this->calculateHarvestingPriority(
                    $loss,
                    $lossPercent,
                    $holdingPeriod,
                    $recoveryAnalysis
                ),
                'rationale' => $this->generateHarvestingRationale(
                    $loss,
                    $lossPercent,
                    $recoveryAnalysis
                ),
            ];
        }

        // Sort by priority (highest first)
        usort($opportunities, function ($a, $b) {
            $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
            $aPriority = $priorityOrder[$a['priority']] ?? 4;
            $bPriority = $priorityOrder[$b['priority']] ?? 4;

            if ($aPriority === $bPriority) {
                return $b['potential_tax_saving'] <=> $a['potential_tax_saving'];
            }

            return $aPriority <=> $bPriority;
        });

        return $opportunities;
    }

    /**
     * Calculate holding period in days
     *
     * @param  Holding  $holding  Holding
     * @return int Days held
     */
    private function calculateHoldingPeriod(Holding $holding): int
    {
        if (! $holding->purchase_date) {
            return 0;
        }

        $purchaseDate = $holding->purchase_date instanceof \DateTime
            ? $holding->purchase_date
            : new \DateTime($holding->purchase_date);

        $now = new \DateTime;
        $interval = $purchaseDate->diff($now);

        return (int) $interval->days;
    }

    /**
     * Analyze recovery potential
     *
     * @param  Holding  $holding  Holding
     * @param  float  $lossPercent  Loss percentage
     * @return array Recovery analysis
     */
    private function analyzeRecoveryPotential(Holding $holding, float $lossPercent): array
    {
        // Simplified analysis - in production would use technical analysis, fundamentals, etc.

        $potential = 'medium'; // Default
        $confidence = 'medium';
        $timeframe = 'medium-term';

        // Large losses suggest potential fundamental issues
        if ($lossPercent > 50) {
            $potential = 'low';
            $confidence = 'low';
            $timeframe = 'long-term';
        } elseif ($lossPercent > 30) {
            $potential = 'medium';
            $confidence = 'medium';
            $timeframe = 'medium-term';
        } else {
            $potential = 'high';
            $confidence = 'medium';
            $timeframe = 'short-term';
        }

        return [
            'potential' => $potential,
            'confidence' => $confidence,
            'timeframe' => $timeframe,
            'recommendation' => $this->getRecoveryRecommendation($potential, $lossPercent),
        ];
    }

    /**
     * Get recovery recommendation
     *
     * @param  string  $potential  Recovery potential
     * @param  float  $lossPercent  Loss percentage
     * @return string Recommendation
     */
    private function getRecoveryRecommendation(string $potential, float $lossPercent): string
    {
        if ($potential === 'low' && $lossPercent > 50) {
            return 'Consider selling at a loss and reinvesting elsewhere';
        }

        if ($potential === 'medium') {
            return 'Sell to use the loss against gains if needed; buying the same shares back within 30 days cancels the loss';
        }

        return 'Temporary decline - consider holding; selling and buying back within 30 days does not release the loss';
    }

    /**
     * Calculate harvesting priority
     *
     * @param  float  $loss  Loss amount
     * @param  float  $lossPercent  Loss percentage
     * @param  int  $holdingPeriod  Days held
     * @param  array  $recoveryAnalysis  Recovery potential
     * @return string Priority (high, medium, low)
     */
    private function calculateHarvestingPriority(
        float $loss,
        float $lossPercent,
        int $holdingPeriod,
        array $recoveryAnalysis
    ): string {
        $score = 0;

        // Large absolute loss
        if ($loss > 5000) {
            $score += 3;
        } elseif ($loss > 2000) {
            $score += 2;
        } elseif ($loss > 1000) {
            $score += 1;
        }

        // Large percentage loss
        if ($lossPercent > 50) {
            $score += 3;
        } elseif ($lossPercent > 30) {
            $score += 2;
        } elseif ($lossPercent > 15) {
            $score += 1;
        }

        // Low recovery potential
        if ($recoveryAnalysis['potential'] === 'low') {
            $score += 2;
        }

        // Long holding period with persistent loss
        if ($holdingPeriod > 365 && $lossPercent > 20) {
            $score += 1;
        }

        if ($score >= 5) {
            return 'high';
        }
        if ($score >= 3) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Generate harvesting rationale
     *
     * @param  float  $loss  Loss amount
     * @param  float  $lossPercent  Loss percentage
     * @param  array  $recoveryAnalysis  Recovery analysis
     * @return string Rationale
     */
    private function generateHarvestingRationale(
        float $loss,
        float $lossPercent,
        array $recoveryAnalysis
    ): string {
        $parts = [];

        $parts[] = sprintf('Realize £%s loss (%.1f%%)', number_format($loss, 0), $lossPercent);

        if ($recoveryAnalysis['potential'] === 'low') {
            $parts[] = 'Low recovery potential - consider reallocating';
        } elseif ($recoveryAnalysis['potential'] === 'high') {
            $parts[] = 'Good recovery potential - can repurchase after 30 days';
        }

        $parts[] = 'Offset future or current year gains';

        return implode('. ', $parts);
    }

    /**
     * Calculate optimal harvesting strategy
     *
     * @param  array  $opportunities  Harvesting opportunities
     * @param  float  $expectedGains  Expected realized gains
     * @param  float  $cgtAllowance  CGT annual allowance
     * @param  float  $lossCarryforward  Existing loss carryforward
     * @param  float  $taxRate  CGT tax rate
     * @return array Optimal strategy
     */
    private function calculateOptimalStrategy(
        array $opportunities,
        float $expectedGains,
        float $cgtAllowance,
        float $lossCarryforward,
        float $taxRate
    ): array {
        $strategy = [
            'harvest_now' => [],
            'harvest_later' => [],
            'total_losses_to_harvest' => 0,
            'total_tax_saving' => 0,
            'explanation' => [],
        ];

        // Calculate taxable gains
        $taxableGains = max(0, $expectedGains - $cgtAllowance - $lossCarryforward);

        if ($taxableGains <= 0 && $expectedGains <= $cgtAllowance) {
            // No taxable gains: a loss realised now is set against this year's
            // gains first, before the annual exempt amount, so there is no tax to
            // save by selling now (HMRC CG21500; tax review F12, item 8). No sale
            // is suggested, whatever a holding might do next.
            $strategy['explanation'][] = 'No taxable gains recorded this tax year, so selling at a loss now saves no tax.';

            return $strategy;
        }

        // Have taxable gains - prioritize harvesting
        $remainingGains = $taxableGains;

        foreach ($opportunities as $opp) {
            if ($remainingGains <= 0) {
                // No more gains to offset - keep rest for future
                $strategy['harvest_later'][] = $opp;

                continue;
            }

            // Harvest this loss
            $lossToUse = min($opp['loss_amount'], $remainingGains);
            $taxSaving = $lossToUse * $taxRate;

            $strategy['harvest_now'][] = $opp;
            $strategy['total_losses_to_harvest'] += $opp['loss_amount'];
            $strategy['total_tax_saving'] += $taxSaving;

            $remainingGains -= $lossToUse;

            $strategy['explanation'][] = sprintf(
                'Sell %s to use the £%s loss and save £%s in Capital Gains Tax',
                $opp['security_name'],
                number_format($opp['loss_amount'], 0),
                number_format($taxSaving, 0)
            );
        }

        return $strategy;
    }

    /**
     * Generate recommendations
     *
     * @param  array  $strategy  Harvesting strategy
     * @param  float  $expectedGains  Expected gains
     * @return array Recommendations
     */
    private function generateRecommendations(array $strategy, float $expectedGains): array
    {
        $recommendations = [];

        if (empty($strategy['harvest_now'])) {
            $recommendations[] = [
                'priority' => 'low',
                'action' => 'No need to sell at a loss now',
                'reason' => 'No taxable gains recorded this tax year, so a loss realised now would save no tax.',
            ];

            return $recommendations;
        }

        // Harvest now recommendations
        foreach ($strategy['harvest_now'] as $opp) {
            $recommendations[] = [
                'priority' => $opp['priority'],
                'action' => sprintf('Sell %s to realize £%s loss', $opp['security_name'], number_format($opp['loss_amount'], 0)),
                'reason' => $opp['rationale'],
                'holding_id' => $opp['holding_id'],
                'loss_amount' => $opp['loss_amount'],
                'tax_saving' => $opp['potential_tax_saving'],
                'repurchase_eligible_date' => $this->getRepurchaseDate(),
                'notes' => [
                    'Buying the same holding back within 30 days matches the sale to the purchase, and the loss is not available (TCGA 1992 s106A).',
                    'A loss not used this tax year carries forward if you report it to HM Revenue and Customs (HMRC) within four years.',
                ],
            ];
        }

        // Year-end urgency
        $monthsToYearEnd = $this->getMonthsToYearEnd();
        if ($monthsToYearEnd <= 2 && count($strategy['harvest_now']) > 0) {
            array_unshift($recommendations, [
                'priority' => 'high',
                'action' => sprintf('Use losses before the tax year ends (%d months remaining)', $monthsToYearEnd),
                'reason' => 'A loss realised after 5 April counts against next tax year\'s gains instead.',
            ]);
        }

        return $recommendations;
    }

    /**
     * Get repurchase eligible date (30 days from now)
     *
     * @return string Date (Y-m-d)
     */
    private function getRepurchaseDate(): string
    {
        $date = new \DateTime;
        $date->modify('+30 days');

        return $date->format('Y-m-d');
    }

    /**
     * Get months to tax year end
     *
     * @return int Months remaining
     */
    private function getMonthsToYearEnd(): int
    {
        $now = new \DateTime;
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');

        // Tax year ends April 5
        if ($currentMonth < 4 || ($currentMonth === 4 && (int) $now->format('d') < 6)) {
            $taxYearEnd = new \DateTime("{$currentYear}-04-05");
        } else {
            $taxYearEnd = new \DateTime(($currentYear + 1).'-04-05');
        }

        $interval = $now->diff($taxYearEnd);
        $months = ($interval->y * 12) + $interval->m;

        if ($interval->d > 0) {
            $months++;
        }

        return max(0, $months);
    }
}
