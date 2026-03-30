<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Constants\TaxDefaults;
use App\Services\TaxConfigService;

/**
 * ConflictResolver
 *
 * Identifies and resolves conflicts between recommendations from different modules.
 * Handles competing demands for:
 * - Cashflow allocation (emergency fund vs. pension vs. investment)
 * - ISA allowance (Cash ISA vs. Stocks & Shares ISA)
 * - Protection vs. savings priorities
 */
class ConflictResolver
{
    /** @var int Adequacy score below which a module is considered critically underserved */
    private const CRITICAL_ADEQUACY_THRESHOLD = 50;

    /** @var float Allocation weight for the higher-priority module when both are critical */
    private const SPLIT_PRIORITY_HIGH_WEIGHT = 0.6;

    /** @var float Allocation weight for the lower-priority module when both are critical */
    private const SPLIT_PRIORITY_LOW_WEIGHT = 0.4;

    /** @var float Allocation weight for the dominant module when one is more severe */
    private const DOMINANT_PRIORITY_WEIGHT = 0.8;

    /** @var float Allocation weight for the subordinate module when one is more severe */
    private const SUBORDINATE_PRIORITY_WEIGHT = 0.2;

    /** @var int Adequacy threshold below which protection/savings conflict is flagged */
    private const CONFLICT_ADEQUACY_THRESHOLD = 75;

    /** @var float Conflict severity ratio threshold for critical */
    private const SEVERITY_CRITICAL_RATIO = 2.0;

    /** @var float Conflict severity ratio threshold for high */
    private const SEVERITY_HIGH_RATIO = 1.5;

    /** @var float Conflict severity ratio threshold for medium */
    private const SEVERITY_MEDIUM_RATIO = 1.2;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Identify conflicts between recommendations from different modules
     *
     * @param  array  $recommendations  All recommendations from all modules
     * @return array Array of identified conflicts
     */
    public function identifyConflicts(array $recommendations): array
    {
        $conflicts = [];

        // Check for cashflow conflicts
        $cashflowConflict = $this->detectCashflowConflicts($recommendations);
        if ($cashflowConflict) {
            $conflicts[] = $cashflowConflict;
        }

        // Check for ISA allowance conflicts
        $isaConflict = $this->detectISAConflicts($recommendations);
        if ($isaConflict) {
            $conflicts[] = $isaConflict;
        }

        // Check for protection vs. savings conflicts
        $protectionSavingsConflict = $this->detectProtectionVsSavingsConflicts($recommendations);
        if ($protectionSavingsConflict) {
            $conflicts[] = $protectionSavingsConflict;
        }

        // Check for estate vs. goals conflicts
        $estateGoalsConflict = $this->detectEstateVsGoalsConflicts($recommendations);
        if ($estateGoalsConflict) {
            $conflicts[] = $estateGoalsConflict;
        }

        return $conflicts;
    }

    /**
     * Resolve conflicts between Protection and Savings recommendations
     *
     * @param  array  $recommendations  All recommendations
     * @return array Resolved recommendations
     */
    public function resolveProtectionVsSavings(array $recommendations): array
    {
        $protectionRecs = $this->filterByModule($recommendations, 'protection');
        $savingsRecs = $this->filterByModule($recommendations, 'savings');

        // If both recommend increased contributions, balance based on adequacy scores
        $protectionAdequacy = $recommendations['module_scores']['protection']['adequacy_score'] ?? 0;
        $emergencyFundAdequacy = $recommendations['module_scores']['savings']['emergency_fund_adequacy'] ?? 0;

        // Priority to whichever has lower adequacy score
        if ($protectionAdequacy < self::CRITICAL_ADEQUACY_THRESHOLD && $emergencyFundAdequacy < self::CRITICAL_ADEQUACY_THRESHOLD) {
            // Both critical - split available funds
            return [
                'resolution' => 'split_priority',
                'allocation' => [
                    'protection' => self::SPLIT_PRIORITY_HIGH_WEIGHT, // Slight priority to protection (risk of death)
                    'savings' => self::SPLIT_PRIORITY_LOW_WEIGHT,
                ],
                'reasoning' => 'Both protection and emergency fund are critically low. Prioritize protection slightly as it addresses catastrophic risk.',
            ];
        } elseif ($protectionAdequacy < $emergencyFundAdequacy) {
            return [
                'resolution' => 'protection_priority',
                'allocation' => [
                    'protection' => self::DOMINANT_PRIORITY_WEIGHT,
                    'savings' => self::SUBORDINATE_PRIORITY_WEIGHT,
                ],
                'reasoning' => 'Protection gap is more severe than emergency fund shortfall.',
            ];
        } else {
            return [
                'resolution' => 'savings_priority',
                'allocation' => [
                    'protection' => self::SUBORDINATE_PRIORITY_WEIGHT,
                    'savings' => self::DOMINANT_PRIORITY_WEIGHT,
                ],
                'reasoning' => 'Emergency fund is more critical than protection gap.',
            ];
        }
    }

    /**
     * Resolve contribution conflicts when multiple modules demand contributions
     *
     * @param  float  $availableSurplus  Monthly surplus available for contributions
     * @param  array  $demands  Contribution demands from each module
     * @return array Optimized allocation
     */
    public function resolveContributionConflicts(float $availableSurplus, array $demands): array
    {
        // Priority order: Emergency fund → Protection → Pension → Investment → Estate → Goals
        $priorityOrder = [
            'emergency_fund' => 1,
            'protection' => 2,
            'pension' => 3,
            'investment' => 4,
            'estate' => 5,
            'goals' => 6,
        ];

        // Sort demands by priority (handle both array and scalar amount formats)
        $sortedDemands = [];
        foreach ($demands as $category => $demandData) {
            // Extract amount from either array format ['amount' => X, 'urgency' => Y] or scalar format
            $amount = is_array($demandData) ? ($demandData['amount'] ?? 0) : $demandData;
            $urgency = is_array($demandData) ? ($demandData['urgency'] ?? 50) : 50;

            $sortedDemands[] = [
                'category' => $category,
                'amount' => $amount,
                'urgency' => $urgency,
                'priority' => $priorityOrder[$category] ?? 999,
            ];
        }

        // Sort by priority first, then by urgency within same priority
        usort($sortedDemands, fn ($a, $b) => $a['priority'] <=> $b['priority'] ?: $b['urgency'] <=> $a['urgency']);

        // Allocate surplus in priority order
        $allocation = [];
        $remaining = $availableSurplus;

        foreach ($sortedDemands as $demand) {
            if ($remaining <= 0) {
                $allocation[$demand['category']] = 0.0;

                continue;
            }

            if ($remaining >= $demand['amount']) {
                // Fully fund this demand
                $allocation[$demand['category']] = (float) $demand['amount'];
                $remaining -= $demand['amount'];
            } else {
                // Partially fund with remaining surplus
                $allocation[$demand['category']] = (float) $remaining;
                $remaining = 0;
            }
        }

        $totalDemand = array_sum(array_column($sortedDemands, 'amount'));

        return [
            'total_demand' => (float) $totalDemand,
            'available_surplus' => $availableSurplus,
            'allocation' => $allocation,
            'shortfall' => (float) max(0, $totalDemand - $availableSurplus),
            'surplus_remaining' => (float) max(0, $remaining),
        ];
    }

    /**
     * Resolve ISA allowance conflicts between Cash ISA and Stocks & Shares ISA
     *
     * @param  float  $isaAllowance  Total ISA allowance (£20,000 for 2025/26)
     * @param  array  $demands  ISA demands from Savings and Investment modules
     * @return array Optimal ISA allocation
     */
    public function resolveISAAllocation(float $isaAllowance, array $demands): array
    {
        $cashISADemand = $demands['cash_isa'] ?? 0;
        $stocksSharesISADemand = $demands['stocks_shares_isa'] ?? 0;
        $totalDemand = $cashISADemand + $stocksSharesISADemand;

        // Get user context for optimal split
        $emergencyFundAdequacy = $demands['emergency_fund_adequacy'] ?? 100;
        $investmentGoalUrgency = $demands['investment_goal_urgency'] ?? 50;
        $riskTolerance = $demands['risk_tolerance'] ?? 'medium';

        // Determine optimal split based on user context
        if ($emergencyFundAdequacy < self::CRITICAL_ADEQUACY_THRESHOLD) {
            // Emergency fund critical - prioritize Cash ISA
            $cashISAAllocation = min($cashISADemand, $isaAllowance);
            $stocksSharesISAAllocation = max(0, $isaAllowance - $cashISAAllocation);
            $reasoning = 'Emergency fund is critically low. Prioritize Cash ISA for liquidity.';
        } elseif ($riskTolerance === 'low') {
            // Low risk tolerance - favor Cash ISA
            $cashISAAllocation = min($cashISADemand, $isaAllowance * 0.7);
            $stocksSharesISAAllocation = min($stocksSharesISADemand, $isaAllowance - $cashISAAllocation);
            $reasoning = 'Low risk tolerance. Favor Cash ISA (70%) over Stocks & Shares ISA (30%).';
        } elseif ($investmentGoalUrgency > 75 && $riskTolerance === 'high') {
            // High growth goals - prioritize Stocks & Shares ISA
            $stocksSharesISAAllocation = min($stocksSharesISADemand, $isaAllowance * 0.9);
            $cashISAAllocation = min($cashISADemand, $isaAllowance - $stocksSharesISAAllocation);
            $reasoning = 'High growth goals with high risk tolerance. Prioritize Stocks & Shares ISA (90%).';
        } else {
            // Balanced approach
            if ($totalDemand <= $isaAllowance) {
                // Can satisfy both
                $cashISAAllocation = $cashISADemand;
                $stocksSharesISAAllocation = $stocksSharesISADemand;
                $reasoning = 'Sufficient ISA allowance to satisfy both Cash ISA and Stocks & Shares ISA demands.';
            } else {
                // Proportional split based on demands
                $cashISAAllocation = ($cashISADemand / $totalDemand) * $isaAllowance;
                $stocksSharesISAAllocation = ($stocksSharesISADemand / $totalDemand) * $isaAllowance;
                $reasoning = 'ISA allowance split proportionally between Cash ISA and Stocks & Shares ISA based on demands.';
            }
        }

        return [
            'total_allowance' => $isaAllowance,
            'total_demand' => $totalDemand,
            'allocation' => [
                'cash_isa' => round($cashISAAllocation, 2),
                'stocks_shares_isa' => round($stocksSharesISAAllocation, 2),
            ],
            'unallocated' => round(max(0, $isaAllowance - $cashISAAllocation - $stocksSharesISAAllocation), 2),
            'shortfall' => round(max(0, $totalDemand - $isaAllowance), 2),
            'reasoning' => $reasoning,
        ];
    }

    /**
     * Detect cashflow conflicts (total demands exceed surplus)
     *
     * @return array|null Conflict details or null
     */
    private function detectCashflowConflicts(array $recommendations): ?array
    {
        $demands = [];
        $totalDemand = 0;

        // Extract contribution demands from each module
        foreach ($recommendations as $module => $moduleRecs) {
            if (! is_array($moduleRecs) || $module === 'module_scores') {
                continue;
            }

            foreach ($moduleRecs as $rec) {
                // Check for both contribution and premium (protection uses premium)
                $amount = $rec['recommended_monthly_contribution'] ?? $rec['recommended_monthly_premium'] ?? 0;

                if ($amount > 0) {
                    $category = $this->mapModuleToCategory($module);
                    $demands[$category] = ($demands[$category] ?? 0) + $amount;
                    $totalDemand += $amount;
                }
            }
        }

        $availableSurplus = $recommendations['available_surplus'] ?? 0;

        if ($totalDemand > $availableSurplus && $totalDemand > 0) {
            return [
                'type' => 'cashflow_conflict',
                'total_demand' => $totalDemand,
                'available_surplus' => $availableSurplus,
                'shortfall' => $totalDemand - $availableSurplus,
                'demands' => $demands,
                'severity' => $this->calculateConflictSeverity($totalDemand, $availableSurplus),
            ];
        }

        return null;
    }

    /**
     * Detect ISA allowance conflicts (demands exceed £20,000 allowance)
     *
     * @return array|null Conflict details or null
     */
    private function detectISAConflicts(array $recommendations): ?array
    {
        // Get ISA allowance from tax configuration
        $isaConfig = $this->taxConfig->getISAAllowances();
        // Fallback to 2025/26 UK ISA allowance if config unavailable
        $isaAllowance = $isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;
        $cashISADemand = 0;
        $stocksSharesISADemand = 0;

        // Check Savings module for Cash ISA demand
        if (isset($recommendations['savings'])) {
            foreach ($recommendations['savings'] as $rec) {
                if (isset($rec['recommended_cash_isa_contribution'])) {
                    $cashISADemand += $rec['recommended_cash_isa_contribution'];
                }
            }
        }

        // Check Investment module for Stocks & Shares ISA demand
        if (isset($recommendations['investment'])) {
            foreach ($recommendations['investment'] as $rec) {
                if (isset($rec['recommended_isa_contribution'])) {
                    $stocksSharesISADemand += $rec['recommended_isa_contribution'];
                }
            }
        }

        $totalISADemand = $cashISADemand + $stocksSharesISADemand;

        if ($totalISADemand > $isaAllowance) {
            return [
                'type' => 'isa_allowance_conflict',
                'total_allowance' => $isaAllowance,
                'total_demand' => $totalISADemand,
                'shortfall' => $totalISADemand - $isaAllowance,
                'demands' => [
                    'cash_isa' => $cashISADemand,
                    'stocks_shares_isa' => $stocksSharesISADemand,
                ],
                'severity' => $this->calculateConflictSeverity($totalISADemand, $isaAllowance),
            ];
        }

        return null;
    }

    /**
     * Detect protection vs. savings conflicts
     *
     * @return array|null Conflict details or null
     */
    private function detectProtectionVsSavingsConflicts(array $recommendations): ?array
    {
        $protectionDemand = 0;
        $savingsDemand = 0;

        // Check Protection module
        if (isset($recommendations['protection'])) {
            foreach ($recommendations['protection'] as $rec) {
                if (isset($rec['recommended_monthly_premium'])) {
                    $protectionDemand += $rec['recommended_monthly_premium'];
                }
            }
        }

        // Check Savings module
        if (isset($recommendations['savings'])) {
            foreach ($recommendations['savings'] as $rec) {
                if (isset($rec['recommended_monthly_contribution'])) {
                    $savingsDemand += $rec['recommended_monthly_contribution'];
                }
            }
        }

        // Conflict exists if both have demands and adequacy scores are low
        $protectionAdequacy = $recommendations['module_scores']['protection']['adequacy_score'] ?? 100;
        $emergencyFundAdequacy = $recommendations['module_scores']['savings']['emergency_fund_adequacy'] ?? 100;

        if ($protectionDemand > 0 && $savingsDemand > 0 && ($protectionAdequacy < self::CONFLICT_ADEQUACY_THRESHOLD || $emergencyFundAdequacy < self::CONFLICT_ADEQUACY_THRESHOLD)) {
            return [
                'type' => 'protection_vs_savings_conflict',
                'protection_demand' => $protectionDemand,
                'savings_demand' => $savingsDemand,
                'protection_adequacy' => $protectionAdequacy,
                'emergency_fund_adequacy' => $emergencyFundAdequacy,
                'severity' => min($protectionAdequacy, $emergencyFundAdequacy) < self::CRITICAL_ADEQUACY_THRESHOLD ? 'high' : 'medium',
            ];
        }

        return null;
    }

    /**
     * Detect conflicts between estate and goal recommendations.
     *
     * Estate gifting/trust strategies may compete with goal contributions
     * for the same disposable income.
     *
     * @return array|null Conflict details or null
     */
    private function detectEstateVsGoalsConflicts(array $recommendations): ?array
    {
        $estateDemand = 0;
        $goalsDemand = 0;

        // Check Estate module for contribution demands
        if (isset($recommendations['estate'])) {
            foreach ($recommendations['estate'] as $rec) {
                $amount = $rec['recommended_monthly_contribution'] ?? $rec['recommended_monthly_premium'] ?? 0;
                $estateDemand += $amount;
            }
        }

        // Check Goals module for contribution demands
        if (isset($recommendations['goals'])) {
            foreach ($recommendations['goals'] as $rec) {
                $amount = $rec['recommended_monthly_contribution'] ?? 0;
                $goalsDemand += $amount;
            }
        }

        $availableSurplus = $recommendations['available_surplus'] ?? 0;

        // Conflict exists when both estate and goals demand funds and combined exceeds surplus
        if ($estateDemand > 0 && $goalsDemand > 0 && ($estateDemand + $goalsDemand) > $availableSurplus) {
            return [
                'type' => 'estate_vs_goals_conflict',
                'estate_demand' => $estateDemand,
                'goals_demand' => $goalsDemand,
                'combined_demand' => $estateDemand + $goalsDemand,
                'available_surplus' => $availableSurplus,
                'shortfall' => ($estateDemand + $goalsDemand) - $availableSurplus,
                'severity' => $this->calculateConflictSeverity($estateDemand + $goalsDemand, $availableSurplus),
            ];
        }

        return null;
    }

    /**
     * Filter recommendations by module
     */
    private function filterByModule(array $recommendations, string $module): array
    {
        return $recommendations[$module] ?? [];
    }

    /**
     * Map module name to contribution category
     */
    private function mapModuleToCategory(string $module): string
    {
        $mapping = [
            'protection' => 'protection',
            'savings' => 'emergency_fund',
            'investment' => 'investment',
            'retirement' => 'pension',
            'estate' => 'estate',
            'goals' => 'goals',
        ];

        return $mapping[$module] ?? $module;
    }

    /**
     * Calculate conflict severity
     */
    private function calculateConflictSeverity(float $demand, float $available): string
    {
        if ($available == 0) {
            return 'critical';
        }

        $ratio = $demand / $available;

        if ($ratio >= self::SEVERITY_CRITICAL_RATIO) {
            return 'critical';
        } elseif ($ratio >= self::SEVERITY_HIGH_RATIO) {
            return 'high';
        } elseif ($ratio >= self::SEVERITY_MEDIUM_RATIO) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
