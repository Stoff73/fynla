<?php

declare(strict_types=1);

namespace App\Services\Investment\Rebalancing;

use App\Models\Investment\Holding;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Tax-aware rebalancing optimizer
 * Minimizes Capital Gains Tax liability when executing rebalancing trades
 *
 * UK CGT Rules (2025/26):
 * - Annual exemption: £3,000
 * - CGT rates: 10% (basic rate) or 20% (higher rate) for most assets
 * - Can offset losses against gains
 */
class TaxAwareRebalancer
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Optimize rebalancing actions to minimize CGT
     *
     * @param  array  $actions  Rebalancing actions from RebalancingCalculator
     * @param  Collection  $holdings  User's holdings (with purchase data for tax lots)
     * @param  array  $options  Options (cgt_allowance, tax_rate, loss_carryforward)
     * @return array Tax-optimized rebalancing plan
     */
    public function optimizeForCGT(
        array $actions,
        Collection $holdings,
        array $options = []
    ): array {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtAllowance = $options['cgt_allowance'] ?? (float) ($cgtConfig['annual_exempt_amount'] ?? 3000);
        $taxRate = $options['tax_rate'] ?? (float) ($cgtConfig['basic_rate'] ?? 0.10);
        $lossCarryforward = $options['loss_carryforward'] ?? 0;

        // Separate buy and sell actions
        $buyActions = collect($actions)->where('action_type', 'buy');
        $sellActions = collect($actions)->where('action_type', 'sell');

        if ($sellActions->isEmpty()) {
            return [
                'success' => true,
                'optimized_actions' => $actions,
                'cgt_analysis' => [
                    'total_gains' => 0,
                    'total_losses' => 0,
                    'net_gains' => 0,
                    'taxable_gains' => 0,
                    'cgt_liability' => 0,
                    'allowance_used' => 0,
                    'allowance_remaining' => $cgtAllowance,
                ],
                'message' => 'No sell actions - no CGT liability',
            ];
        }

        // Calculate CGT for each sell action
        $sellActionsWithCGT = $this->calculateCGTForSellActions(
            $sellActions,
            $holdings,
            $cgtAllowance,
            $taxRate,
            $lossCarryforward
        );

        // Optimize sell order to minimize CGT
        $optimizedSellActions = $this->optimizeSellOrder(
            $sellActionsWithCGT,
            $cgtAllowance,
            $lossCarryforward
        );

        // Calculate total CGT liability
        $cgtAnalysis = $this->calculateTotalCGT(
            $optimizedSellActions,
            $cgtAllowance,
            $taxRate,
            $lossCarryforward
        );

        // Combine optimized sell actions with buy actions
        $optimizedActions = $optimizedSellActions->merge($buyActions)->values()->all();

        // Generate tax-loss harvesting opportunities
        $taxLossOpportunities = $this->identifyTaxLossHarvesting(
            $holdings,
            $cgtAnalysis,
            $cgtAllowance
        );

        return [
            'success' => true,
            'optimized_actions' => $optimizedActions,
            'cgt_analysis' => $cgtAnalysis,
            'tax_loss_opportunities' => $taxLossOpportunities,
            'summary' => $this->generateCGTSummary($cgtAnalysis, $taxLossOpportunities),
        ];
    }

    /**
     * Calculate CGT for each sell action
     *
     * @param  Collection  $sellActions  Sell actions
     * @param  Collection  $holdings  Holdings with purchase data
     * @param  float  $cgtAllowance  Annual CGT allowance
     * @param  float  $taxRate  CGT tax rate
     * @param  float  $lossCarryforward  Losses carried forward from previous years
     * @return Collection Sell actions with CGT data
     */
    private function calculateCGTForSellActions(
        Collection $sellActions,
        Collection $holdings,
        float $cgtAllowance,
        float $taxRate,
        float $lossCarryforward
    ): Collection {
        return $sellActions->map(function ($action) use ($holdings, $taxRate) {
            $holding = $holdings->firstWhere('id', $action['holding_id']);

            if (! $holding) {
                return array_merge($action, [
                    'cgt_data' => null,
                    'gain_or_loss' => 0,
                    'cgt_liability' => 0,
                ]);
            }

            // Calculate gain/loss
            $costBasis = $holding->purchase_price * $action['shares_to_trade'];
            $proceeds = $holding->current_price * $action['shares_to_trade'];
            $gainOrLoss = $proceeds - $costBasis;

            // Calculate potential CGT (before allowance)
            $potentialCGT = $gainOrLoss > 0 ? $gainOrLoss * $taxRate : 0;

            return array_merge($action, [
                'cgt_data' => [
                    'cost_basis' => $costBasis,
                    'proceeds' => $proceeds,
                    'gain_or_loss' => $gainOrLoss,
                    'holding_period_days' => $this->calculateHoldingPeriod($holding),
                ],
                'gain_or_loss' => $gainOrLoss,
                'potential_cgt' => $potentialCGT,
            ]);
        });
    }

    /**
     * Optimize the order of sell actions to minimize CGT
     *
     * Strategy:
     * 1. Sell loss-making positions first (to offset gains)
     * 2. Sell positions with smallest gains (to maximize allowance usage)
     * 3. Consider holding period (longer-held assets first if tax benefits)
     *
     * @param  Collection  $sellActions  Sell actions with CGT data
     * @param  float  $cgtAllowance  Annual CGT allowance
     * @param  float  $lossCarryforward  Losses carried forward
     * @return Collection Optimized sell actions
     */
    private function optimizeSellOrder(
        Collection $sellActions,
        float $cgtAllowance,
        float $lossCarryforward
    ): Collection {
        // Separate losses and gains
        $losses = $sellActions->filter(fn ($action) => $action['gain_or_loss'] < 0);
        $gains = $sellActions->filter(fn ($action) => $action['gain_or_loss'] >= 0);

        // Sort losses by largest loss first (maximize offset potential)
        $sortedLosses = $losses->sortBy('gain_or_loss');

        // Sort gains by smallest gain first (maximize allowance usage efficiency)
        $sortedGains = $gains->sortBy('gain_or_loss');

        // Combine: losses first, then gains
        return $sortedLosses->merge($sortedGains)->values();
    }

    /**
     * Calculate total CGT liability across all sell actions
     *
     * @param  Collection  $sellActions  Optimized sell actions with CGT data
     * @param  float  $cgtAllowance  Annual CGT allowance
     * @param  float  $taxRate  CGT tax rate
     * @param  float  $lossCarryforward  Losses carried forward from previous years
     * @return array CGT analysis
     */
    private function calculateTotalCGT(
        Collection $sellActions,
        float $cgtAllowance,
        float $taxRate,
        float $lossCarryforward
    ): array {
        $totalGains = $sellActions->where('gain_or_loss', '>', 0)->sum('gain_or_loss');
        $totalLosses = abs($sellActions->where('gain_or_loss', '<', 0)->sum('gain_or_loss'));

        // Net gains after offsetting losses
        $netGains = $totalGains - $totalLosses;

        // Apply loss carryforward
        $netGainsAfterCarryforward = max(0, $netGains - $lossCarryforward);

        // Apply annual allowance
        $taxableGains = max(0, $netGainsAfterCarryforward - $cgtAllowance);

        // Calculate CGT liability
        $cgtLiability = $taxableGains * $taxRate;

        // Allowance used
        $allowanceUsed = min($cgtAllowance, $netGainsAfterCarryforward);
        $allowanceRemaining = $cgtAllowance - $allowanceUsed;

        return [
            'total_gains' => round($totalGains, 2),
            'total_losses' => round($totalLosses, 2),
            'net_gains' => round($netGains, 2),
            'loss_carryforward_used' => round(min($lossCarryforward, $netGains), 2),
            'net_gains_after_carryforward' => round($netGainsAfterCarryforward, 2),
            'allowance_used' => round($allowanceUsed, 2),
            'allowance_remaining' => round($allowanceRemaining, 2),
            'taxable_gains' => round($taxableGains, 2),
            'cgt_liability' => round($cgtLiability, 2),
            'effective_tax_rate' => $totalGains > 0 ? round(($cgtLiability / $totalGains) * 100, 2) : 0,
        ];
    }

    /**
     * Identify tax-loss harvesting opportunities
     *
     * Find holdings with unrealized losses that could be sold to offset gains
     *
     * @param  Collection  $holdings  All user holdings
     * @param  array  $cgtAnalysis  CGT analysis from rebalancing
     * @param  float  $cgtAllowance  Annual CGT allowance
     * @return array Tax-loss harvesting opportunities
     */
    private function identifyTaxLossHarvesting(
        Collection $holdings,
        array $cgtAnalysis,
        float $cgtAllowance
    ): array {
        // Only consider if there are taxable gains
        if ($cgtAnalysis['taxable_gains'] <= 0) {
            return [
                'opportunities' => [],
                'potential_tax_saving' => 0,
                'message' => 'No taxable gains - tax-loss harvesting not beneficial',
            ];
        }

        $opportunities = [];

        foreach ($holdings as $holding) {
            // Skip if no purchase data
            if (! $holding->purchase_price || ! $holding->current_price) {
                continue;
            }

            // Calculate unrealized gain/loss
            $costBasis = $holding->purchase_price * $holding->quantity;
            $currentValue = $holding->current_price * $holding->quantity;
            $unrealizedGainLoss = $currentValue - $costBasis;

            // Only consider losses
            if ($unrealizedGainLoss >= 0) {
                continue;
            }

            $opportunities[] = [
                'holding_id' => $holding->id,
                'security_name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'unrealized_loss' => abs($unrealizedGainLoss),
                'current_value' => $currentValue,
                'cost_basis' => $costBasis,
                'holding_period_days' => $this->calculateHoldingPeriod($holding),
                'potential_tax_benefit' => abs($unrealizedGainLoss) * 0.20, // Assume 20% rate
                'rationale' => sprintf(
                    'Sell to realize £%s loss, offsetting gains and reducing CGT',
                    number_format(abs($unrealizedGainLoss), 2)
                ),
            ];
        }

        // Sort by largest loss first
        usort($opportunities, fn ($a, $b) => $b['unrealized_loss'] <=> $a['unrealized_loss']);

        // Calculate potential total tax saving
        $totalPotentialLosses = array_sum(array_column($opportunities, 'unrealized_loss'));
        $potentialTaxSaving = min($totalPotentialLosses, $cgtAnalysis['taxable_gains']) * 0.20;

        return [
            'opportunities' => $opportunities,
            'total_potential_losses' => round($totalPotentialLosses, 2),
            'potential_tax_saving' => round($potentialTaxSaving, 2),
            'message' => count($opportunities) > 0
                ? sprintf(
                    'Found %d tax-loss harvesting opportunity/opportunities with potential £%s tax saving',
                    count($opportunities),
                    number_format($potentialTaxSaving, 2)
                )
                : 'No tax-loss harvesting opportunities identified',
        ];
    }

    /**
     * Calculate holding period in days
     *
     * @param  Holding  $holding  Holding instance
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
     * Generate CGT summary text
     *
     * @param  array  $cgtAnalysis  CGT analysis
     * @param  array  $taxLossOpportunities  Tax-loss harvesting opportunities
     * @return string Summary text
     */
    private function generateCGTSummary(array $cgtAnalysis, array $taxLossOpportunities): string
    {
        $parts = [];

        if ($cgtAnalysis['total_gains'] === 0 && $cgtAnalysis['total_losses'] === 0) {
            return 'No capital gains or losses from rebalancing.';
        }

        // Gains and losses
        if ($cgtAnalysis['total_gains'] > 0) {
            $parts[] = sprintf(
                'Total gains: £%s',
                number_format($cgtAnalysis['total_gains'], 2)
            );
        }

        if ($cgtAnalysis['total_losses'] > 0) {
            $parts[] = sprintf(
                'Total losses: £%s (offset against gains)',
                number_format($cgtAnalysis['total_losses'], 2)
            );
        }

        // Net gains
        $parts[] = sprintf(
            'Net gains: £%s',
            number_format($cgtAnalysis['net_gains'], 2)
        );

        // Allowance
        if ($cgtAnalysis['allowance_used'] > 0) {
            $parts[] = sprintf(
                'CGT allowance used: £%s',
                number_format($cgtAnalysis['allowance_used'], 2)
            );
        }

        // CGT liability
        if ($cgtAnalysis['cgt_liability'] > 0) {
            $parts[] = sprintf(
                'Estimated CGT liability: £%s (effective rate: %s%%)',
                number_format($cgtAnalysis['cgt_liability'], 2),
                $cgtAnalysis['effective_tax_rate']
            );
        } else {
            $parts[] = 'No CGT liability (gains within allowance)';
        }

        // Tax-loss harvesting
        if ($taxLossOpportunities['potential_tax_saving'] > 0) {
            $parts[] = sprintf(
                'Potential tax saving from loss harvesting: £%s',
                number_format($taxLossOpportunities['potential_tax_saving'], 2)
            );
        }

        return implode('. ', $parts).'.';
    }

    /**
     * Compare CGT liability between different rebalancing strategies
     *
     * @param  Collection  $holdings  User holdings
     * @param  array  $strategy1Actions  Actions from strategy 1
     * @param  array  $strategy2Actions  Actions from strategy 2
     * @param  array  $options  CGT options
     * @return array Comparison result
     */
    public function compareStrategies(
        Collection $holdings,
        array $strategy1Actions,
        array $strategy2Actions,
        array $options = []
    ): array {
        $result1 = $this->optimizeForCGT($strategy1Actions, $holdings, $options);
        $result2 = $this->optimizeForCGT($strategy2Actions, $holdings, $options);

        $cgtDifference = $result1['cgt_analysis']['cgt_liability'] - $result2['cgt_analysis']['cgt_liability'];

        return [
            'strategy_1' => [
                'cgt_liability' => $result1['cgt_analysis']['cgt_liability'],
                'net_gains' => $result1['cgt_analysis']['net_gains'],
                'num_actions' => count($strategy1Actions),
            ],
            'strategy_2' => [
                'cgt_liability' => $result2['cgt_analysis']['cgt_liability'],
                'net_gains' => $result2['cgt_analysis']['net_gains'],
                'num_actions' => count($strategy2Actions),
            ],
            'cgt_difference' => round($cgtDifference, 2),
            'preferred_strategy' => $cgtDifference > 0 ? 2 : 1,
            'recommendation' => $cgtDifference > 0
                ? sprintf('Strategy 2 saves £%s in CGT', number_format(abs($cgtDifference), 2))
                : sprintf('Strategy 1 saves £%s in CGT', number_format(abs($cgtDifference), 2)),
        ];
    }

    /**
     * Calculate CGT-aware efficient rebalancing
     *
     * Determines minimum trades needed to stay within CGT allowance
     *
     * @param  array  $actions  Proposed rebalancing actions
     * @param  Collection  $holdings  User holdings
     * @param  array  $options  Options including cgt_allowance
     * @return array Modified actions that stay within CGT allowance
     */
    public function rebalanceWithinCGTAllowance(
        array $actions,
        Collection $holdings,
        array $options = []
    ): array {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtAllowance = $options['cgt_allowance'] ?? (float) ($cgtConfig['annual_exempt_amount'] ?? 3000);
        $taxRate = $options['tax_rate'] ?? (float) ($cgtConfig['basic_rate'] ?? 0.10);

        // Calculate CGT for all actions
        $actionsWithCGT = $this->calculateCGTForSellActions(
            collect($actions)->where('action_type', 'sell'),
            $holdings,
            $cgtAllowance,
            $taxRate,
            0
        );

        // Sort by gain/loss (losses first, then smallest gains)
        $sortedActions = $actionsWithCGT->sortBy('gain_or_loss')->values();

        // Select actions until we hit CGT allowance
        $selectedActions = [];
        $cumulativeGains = 0;

        foreach ($sortedActions as $action) {
            $gainOrLoss = $action['gain_or_loss'];

            // Always include losses (they reduce taxable gains)
            if ($gainOrLoss < 0) {
                $selectedActions[] = $action;
                $cumulativeGains += $gainOrLoss;

                continue;
            }

            // Check if this gain would exceed allowance
            if (($cumulativeGains + $gainOrLoss) <= $cgtAllowance) {
                $selectedActions[] = $action;
                $cumulativeGains += $gainOrLoss;
            } else {
                // Partially execute this trade to use remaining allowance
                $remainingAllowance = $cgtAllowance - $cumulativeGains;

                if ($remainingAllowance > 100) { // Only if remaining is meaningful
                    // Scale down the trade
                    $scaleFactor = $remainingAllowance / $gainOrLoss;
                    $partialAction = $action;
                    $partialAction['shares_to_trade'] *= $scaleFactor;
                    $partialAction['trade_value'] *= $scaleFactor;
                    $partialAction['gain_or_loss'] = $remainingAllowance;
                    $partialAction['partial_execution'] = true;

                    $selectedActions[] = $partialAction;
                }

                break; // Stop adding more actions
            }
        }

        // Add all buy actions (no CGT impact)
        $buyActions = collect($actions)->where('action_type', 'buy');
        $selectedActions = array_merge($selectedActions, $buyActions->all());

        return [
            'success' => true,
            'modified_actions' => $selectedActions,
            'original_action_count' => count($actions),
            'modified_action_count' => count($selectedActions),
            'actions_deferred' => count($actions) - count($selectedActions),
            'total_gains_within_allowance' => round($cumulativeGains, 2),
            'message' => sprintf(
                'Modified rebalancing to stay within £%s CGT allowance. %d action(s) deferred.',
                number_format($cgtAllowance, 2),
                count($actions) - count($selectedActions)
            ),
        ];
    }
}
