<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Agents\EstateAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;

class DashboardAggregator
{
    public function __construct(
        private readonly ProtectionAgent $protectionAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent
    ) {}

    /**
     * Aggregate overview data from all modules
     */
    public function aggregateOverviewData(int $userId): array
    {
        try {
            return [
                'protection' => $this->getProtectionSummary($userId),
                'savings' => $this->getSavingsSummary($userId),
                'investment' => $this->getInvestmentSummary($userId),
                'retirement' => $this->getRetirementSummary($userId),
                'estate' => $this->getEstateSummary($userId),
            ];
        } catch (\Exception $e) {
            // Log error but don't fail entirely - return partial data
            \Log::error('Failed to aggregate dashboard data: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Aggregate and prioritize alerts from all modules
     */
    public function aggregateAlerts(int $userId): array
    {
        try {
            $alerts = [];

            // Collect alerts from each module
            $alerts = array_merge($alerts, $this->getProtectionAlerts($userId));
            $alerts = array_merge($alerts, $this->getSavingsAlerts($userId));
            $alerts = array_merge($alerts, $this->getInvestmentAlerts($userId));
            $alerts = array_merge($alerts, $this->getRetirementAlerts($userId));
            $alerts = array_merge($alerts, $this->getEstateAlerts($userId));

            // Sort by severity (critical > important > info)
            usort($alerts, function ($a, $b) {
                $severityOrder = ['critical' => 0, 'important' => 1, 'info' => 2];

                return ($severityOrder[$a['severity']] ?? 2) <=> ($severityOrder[$b['severity']] ?? 2);
            });

            return $alerts;
        } catch (\Exception $e) {
            \Log::error('Failed to aggregate alerts: '.$e->getMessage());

            return [];
        }
    }

    // ─── Agent data retrieval helpers ──────────────────────────────────

    /**
     * Safely call ProtectionAgent->analyze() and return the data array.
     * Returns null when the agent fails or has no usable data.
     */
    private function getProtectionAnalysis(int $userId): ?array
    {
        try {
            $result = $this->protectionAgent->analyze($userId);

            // ProtectionAgent uses response() wrapper: ['success','message','data','timestamp']
            if (! ($result['success'] ?? false)) {
                return null;
            }

            $data = $result['data'] ?? [];

            // Readiness gate returned early — no usable data
            if (isset($data['can_proceed']) && $data['can_proceed'] === false) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: ProtectionAgent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Safely call SavingsAgent->analyze() and return the data array.
     * SavingsAgent returns the data directly (no response() wrapper).
     */
    private function getSavingsAnalysis(int $userId): ?array
    {
        try {
            $result = $this->savingsAgent->analyze($userId);

            // Readiness gate returned early
            if (isset($result['can_proceed']) && $result['can_proceed'] === false) {
                return null;
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: SavingsAgent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Safely call InvestmentAgent->analyze() and return the data array.
     * InvestmentAgent returns the data directly (no response() wrapper).
     */
    private function getInvestmentAnalysis(int $userId): ?array
    {
        try {
            $result = $this->investmentAgent->analyze($userId);

            // Readiness gate returned early
            if (isset($result['can_proceed']) && $result['can_proceed'] === false) {
                return null;
            }

            // No accounts found
            if (($result['accounts_count'] ?? -1) === 0) {
                return null;
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: InvestmentAgent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Safely call RetirementAgent->analyze() and return the data array.
     * RetirementAgent uses response() wrapper: ['success','message','data','timestamp']
     */
    private function getRetirementAnalysis(int $userId): ?array
    {
        try {
            $result = $this->retirementAgent->analyze($userId);

            if (! ($result['success'] ?? false)) {
                return null;
            }

            $data = $result['data'] ?? [];

            if (isset($data['can_proceed']) && $data['can_proceed'] === false) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: RetirementAgent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Safely call EstateAgent->analyze() and return the data array.
     * EstateAgent uses response() wrapper: ['success','message','data','timestamp']
     */
    private function getEstateAnalysis(int $userId): ?array
    {
        try {
            $result = $this->estateAgent->analyze($userId);

            if (! ($result['success'] ?? false)) {
                return null;
            }

            $data = $result['data'] ?? [];

            if (isset($data['can_proceed']) && $data['can_proceed'] === false) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: EstateAgent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    // ─── Summary methods ───────────────────────────────────────────────

    private function getProtectionSummary(int $userId): array
    {
        $data = $this->getProtectionAnalysis($userId);

        if ($data === null) {
            return [
                'adequacy_score' => 0,
                'total_coverage' => 0,
                'premium_total' => 0,
                'critical_gaps' => 0,
            ];
        }

        // adequacy_score lives at data.adequacy_score.overall_score
        $adequacyScore = (float) ($data['adequacy_score']['overall_score'] ?? 0);

        // Total coverage from the coverage summary
        $totalCoverage = (float) ($data['coverage']['total_coverage'] ?? 0);

        // Total premium: sum premiums across all policy types
        $totalPremium = (float) ($data['coverage']['total_premium'] ?? 0);

        // Count critical gaps
        $gaps = $data['gaps'] ?? [];
        $criticalGaps = 0;
        foreach ($gaps as $gap) {
            if (is_array($gap) && ($gap['shortfall'] ?? 0) > 0) {
                $criticalGaps++;
            }
        }

        return [
            'adequacy_score' => round($adequacyScore, 2),
            'total_coverage' => round($totalCoverage, 2),
            'premium_total' => round($totalPremium, 2),
            'critical_gaps' => $criticalGaps,
        ];
    }

    private function getSavingsSummary(int $userId): array
    {
        $data = $this->getSavingsAnalysis($userId);

        if ($data === null) {
            return [
                'emergency_fund_runway' => 0,
                'total_savings' => 0,
                'isa_usage_percent' => 0,
                'goals_on_track' => 0,
            ];
        }

        $totalSavings = (float) ($data['summary']['total_savings'] ?? 0);
        $runwayMonths = (float) ($data['emergency_fund']['runway_months'] ?? 0);

        // ISA usage: calculate percentage of allowance used
        $isaUsed = (float) ($data['isa_allowance']['used'] ?? 0);
        $isaAllowance = (float) ($data['isa_allowance']['total_allowance'] ?? \App\Constants\TaxDefaults::ISA_ALLOWANCE);
        $isaUsagePercent = $isaAllowance > 0 ? round(($isaUsed / $isaAllowance) * 100, 2) : 0;

        // Count goals on track (progress >= 75% indicates on track)
        $goalsOnTrack = 0;
        $goalsProgress = $data['goals']['progress'] ?? [];
        foreach ($goalsProgress as $goalEntry) {
            $progress = $goalEntry['progress'] ?? [];
            $percentComplete = (float) ($progress['percentage_complete'] ?? $progress['progress_percent'] ?? 0);
            if ($percentComplete >= 75) {
                $goalsOnTrack++;
            }
        }

        return [
            'emergency_fund_runway' => round($runwayMonths, 1),
            'total_savings' => round($totalSavings, 2),
            'isa_usage_percent' => $isaUsagePercent,
            'goals_on_track' => $goalsOnTrack,
        ];
    }

    private function getInvestmentSummary(int $userId): array
    {
        $data = $this->getInvestmentAnalysis($userId);

        if ($data === null) {
            return [
                'portfolio_value' => 0,
                'ytd_return' => 0,
                'holdings_count' => 0,
                'needs_rebalancing' => false,
            ];
        }

        $portfolioValue = (float) ($data['portfolio_summary']['total_value'] ?? 0);
        $holdingsCount = (int) ($data['portfolio_summary']['holdings_count'] ?? 0);
        $ytdReturn = (float) ($data['returns']['ytd_return'] ?? $data['returns']['ytd'] ?? 0);

        // Needs rebalancing: check if any allocation deviation exceeds threshold
        $needsRebalancing = false;
        $deviation = $data['allocation_deviation'] ?? null;
        if (is_array($deviation)) {
            foreach ($deviation as $entry) {
                $dev = abs((float) ($entry['deviation'] ?? $entry['deviation_percent'] ?? 0));
                if ($dev > 5.0) {
                    $needsRebalancing = true;

                    break;
                }
            }
        }

        return [
            'portfolio_value' => round($portfolioValue, 2),
            'ytd_return' => round($ytdReturn, 2),
            'holdings_count' => $holdingsCount,
            'needs_rebalancing' => $needsRebalancing,
        ];
    }

    private function getRetirementSummary(int $userId): array
    {
        $data = $this->getRetirementAnalysis($userId);

        if ($data === null) {
            return [
                'projected_income' => 0,
                'target_income' => 0,
                'income_gap' => 0,
                'years_to_retirement' => 0,
            ];
        }

        $summary = $data['summary'] ?? [];

        return [
            'projected_income' => round((float) ($summary['projected_retirement_income'] ?? 0), 2),
            'target_income' => round((float) ($summary['target_retirement_income'] ?? 0), 2),
            'income_gap' => round((float) ($summary['income_gap'] ?? 0), 2),
            'years_to_retirement' => (int) ($summary['years_to_retirement'] ?? 0),
        ];
    }

    private function getEstateSummary(int $userId): array
    {
        $data = $this->getEstateAnalysis($userId);

        if ($data === null) {
            return [
                'net_worth' => 0,
                'iht_liability' => 0,
                'effective_tax_rate' => 0,
            ];
        }

        $summary = $data['summary'] ?? [];

        return [
            'net_worth' => round((float) ($summary['net_estate'] ?? 0), 2),
            'iht_liability' => round((float) ($summary['iht_liability'] ?? 0), 2),
            'effective_tax_rate' => round((float) ($summary['effective_tax_rate'] ?? 0), 2),
        ];
    }

    // ─── Alert methods ─────────────────────────────────────────────────

    private function getProtectionAlerts(int $userId): array
    {
        try {
            $data = $this->getProtectionAnalysis($userId);

            if ($data === null) {
                return [];
            }

            $alerts = [];
            $alertId = 100;

            // Alert for each coverage gap with a shortfall
            $gaps = $data['gaps'] ?? [];
            foreach ($gaps as $gapType => $gap) {
                if (is_array($gap) && ($gap['shortfall'] ?? 0) > 0) {
                    $severity = ($gap['shortfall'] > 100000) ? 'critical' : 'important';
                    $alerts[] = [
                        'id' => $alertId++,
                        'module' => 'Protection',
                        'severity' => $severity,
                        'title' => ucfirst(str_replace('_', ' ', (string) $gapType)).' Gap',
                        'message' => sprintf(
                            'Your %s coverage has a shortfall. Review your protection to ensure adequate cover.',
                            str_replace('_', ' ', (string) $gapType)
                        ),
                        'action_link' => '/protection',
                        'action_text' => 'Review Coverage',
                        'created_at' => now()->toISOString(),
                    ];
                }
            }

            // Alert for low adequacy score
            $adequacyScore = (float) ($data['adequacy_score']['overall_score'] ?? 100);
            if ($adequacyScore < 50) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Protection',
                    'severity' => 'critical',
                    'title' => 'Protection Adequacy Low',
                    'message' => 'Your overall protection adequacy is below recommended levels. Urgent review advised.',
                    'action_link' => '/protection',
                    'action_text' => 'Review Protection',
                    'created_at' => now()->toISOString(),
                ];
            }

            return $alerts;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: Protection alerts failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getSavingsAlerts(int $userId): array
    {
        try {
            $data = $this->getSavingsAnalysis($userId);

            if ($data === null) {
                return [];
            }

            $alerts = [];
            $alertId = 200;

            // Emergency fund alerts
            $runway = (float) ($data['emergency_fund']['runway_months'] ?? 0);
            $targetMonths = (float) ($data['emergency_fund']['target']['target_months'] ?? 6);

            if ($runway < 3) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Savings',
                    'severity' => 'critical',
                    'title' => 'Emergency Fund Critical',
                    'message' => sprintf(
                        'Your emergency fund covers only %.1f months of expenses. Target is %d months. Immediate action recommended.',
                        $runway,
                        (int) $targetMonths
                    ),
                    'action_link' => '/savings',
                    'action_text' => 'Build Emergency Fund',
                    'created_at' => now()->toISOString(),
                ];
            } elseif ($runway < $targetMonths) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Savings',
                    'severity' => 'important',
                    'title' => 'Emergency Fund Below Target',
                    'message' => sprintf(
                        'Your emergency fund covers %.1f months. Target is %d months.',
                        $runway,
                        (int) $targetMonths
                    ),
                    'action_link' => '/savings',
                    'action_text' => 'Add to Emergency Fund',
                    'created_at' => now()->toISOString(),
                ];
            }

            // ISA allowance alert (if significant allowance unused and tax year end approaching)
            $isaRemaining = (float) ($data['isa_allowance']['remaining'] ?? 0);
            if ($isaRemaining > 5000) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Savings',
                    'severity' => 'info',
                    'title' => 'ISA Allowance Available',
                    'message' => sprintf(
                        'You have %s of ISA allowance remaining this tax year. Consider using your tax-efficient savings allowance.',
                        number_format($isaRemaining, 0, '.', ',')
                    ),
                    'action_link' => '/savings',
                    'action_text' => 'Review ISA Options',
                    'created_at' => now()->toISOString(),
                ];
            }

            // FSCS exposure alert
            $fscsExposure = $data['fscs_exposure'] ?? null;
            if (is_array($fscsExposure) && ($fscsExposure['has_excess_exposure'] ?? false)) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Savings',
                    'severity' => 'important',
                    'title' => 'Financial Services Compensation Scheme Exposure',
                    'message' => 'You have deposits exceeding the Financial Services Compensation Scheme protection limit at one or more institutions.',
                    'action_link' => '/savings',
                    'action_text' => 'Review Deposit Spread',
                    'created_at' => now()->toISOString(),
                ];
            }

            return $alerts;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: Savings alerts failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getInvestmentAlerts(int $userId): array
    {
        try {
            $data = $this->getInvestmentAnalysis($userId);

            if ($data === null) {
                return [];
            }

            $alerts = [];
            $alertId = 300;

            // Rebalancing alert
            $deviation = $data['allocation_deviation'] ?? null;
            $maxDeviation = 0.0;
            if (is_array($deviation)) {
                foreach ($deviation as $entry) {
                    $dev = abs((float) ($entry['deviation'] ?? $entry['deviation_percent'] ?? 0));
                    $maxDeviation = max($maxDeviation, $dev);
                }
            }
            if ($maxDeviation > 5.0) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Investment',
                    'severity' => $maxDeviation > 10.0 ? 'important' : 'info',
                    'title' => 'Portfolio Rebalancing Needed',
                    'message' => sprintf(
                        'Your asset allocation has drifted up to %.1f%% from your target. Consider rebalancing.',
                        $maxDeviation
                    ),
                    'action_link' => '/investment',
                    'action_text' => 'Review Allocation',
                    'created_at' => now()->toISOString(),
                ];
            }

            // High fees alert
            $feeAnalysis = $data['fee_analysis'] ?? [];
            $highFeeHoldings = $feeAnalysis['high_fee_holdings'] ?? [];
            if (is_array($highFeeHoldings) && count($highFeeHoldings) > 0) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Investment',
                    'severity' => 'info',
                    'title' => 'High Fee Holdings Identified',
                    'message' => sprintf(
                        '%d holding(s) have fees above recommended levels. Switching to lower-cost alternatives could improve returns.',
                        count($highFeeHoldings)
                    ),
                    'action_link' => '/investment',
                    'action_text' => 'Review Fees',
                    'created_at' => now()->toISOString(),
                ];
            }

            // Tax harvesting opportunities
            $taxEfficiency = $data['tax_efficiency'] ?? [];
            $harvestingOpportunities = $taxEfficiency['harvesting_opportunities'] ?? [];
            if (is_array($harvestingOpportunities) && count($harvestingOpportunities) > 0) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Investment',
                    'severity' => 'info',
                    'title' => 'Tax Loss Harvesting Opportunity',
                    'message' => 'There are holdings with unrealised losses that could be harvested to offset gains.',
                    'action_link' => '/investment',
                    'action_text' => 'Review Tax Efficiency',
                    'created_at' => now()->toISOString(),
                ];
            }

            // ISA wrapper usage
            $taxWrappers = $data['tax_wrappers'] ?? [];
            $isaRemaining = (float) ($taxWrappers['isa_remaining'] ?? 0);
            $giaValue = (float) ($taxWrappers['gia_value'] ?? 0);
            if ($isaRemaining > 0 && $giaValue > 0) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Investment',
                    'severity' => 'info',
                    'title' => 'ISA Shelter Opportunity',
                    'message' => 'You hold investments in a General Investment Account while ISA allowance remains. Consider sheltering investments in an ISA.',
                    'action_link' => '/investment',
                    'action_text' => 'Review Tax Wrappers',
                    'created_at' => now()->toISOString(),
                ];
            }

            return $alerts;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: Investment alerts failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getRetirementAlerts(int $userId): array
    {
        try {
            $data = $this->getRetirementAnalysis($userId);

            if ($data === null) {
                return [];
            }

            $alerts = [];
            $alertId = 400;

            $summary = $data['summary'] ?? [];
            $incomeGap = (float) ($summary['income_gap'] ?? 0);
            $yearsToRetirement = (int) ($summary['years_to_retirement'] ?? 0);

            // Income gap alert
            if ($incomeGap > 0) {
                $severity = ($incomeGap > 10000 || $yearsToRetirement < 10) ? 'critical' : 'important';
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Retirement',
                    'severity' => $severity,
                    'title' => 'Retirement Income Gap',
                    'message' => sprintf(
                        'Your projected retirement income is %s below your target. %s',
                        number_format($incomeGap, 0, '.', ','),
                        $yearsToRetirement < 10
                            ? 'With less than 10 years to retirement, early action is important.'
                            : 'Consider increasing contributions to close the gap.'
                    ),
                    'action_link' => '/retirement',
                    'action_text' => 'Review Retirement Plan',
                    'created_at' => now()->toISOString(),
                ];
            }

            // Annual allowance opportunity
            $allowance = $data['annual_allowance'] ?? [];
            $aaRemaining = (float) ($allowance['remaining'] ?? $allowance['unused_allowance'] ?? 0);
            if ($aaRemaining > 5000) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Retirement',
                    'severity' => 'info',
                    'title' => 'Pension Contribution Opportunity',
                    'message' => sprintf(
                        'You have %s of unused annual allowance. Maximising pension contributions provides tax relief.',
                        number_format($aaRemaining, 0, '.', ',')
                    ),
                    'action_link' => '/retirement',
                    'action_text' => 'Optimise Contributions',
                    'created_at' => now()->toISOString(),
                ];
            }

            // Early retirement warning (retiring before State Pension Age)
            if ($summary['retires_before_spa'] ?? false) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Retirement',
                    'severity' => 'important',
                    'title' => 'Early Retirement Bridging Required',
                    'message' => sprintf(
                        'You plan to retire before your State Pension age of %d. You will need to bridge the income gap until your State Pension begins.',
                        $summary['state_pension_age'] ?? 67
                    ),
                    'action_link' => '/retirement',
                    'action_text' => 'Plan Bridging Strategy',
                    'created_at' => now()->toISOString(),
                ];
            }

            return $alerts;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: Retirement alerts failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getEstateAlerts(int $userId): array
    {
        try {
            $data = $this->getEstateAnalysis($userId);

            if ($data === null) {
                return [];
            }

            $alerts = [];
            $alertId = 500;

            // IHT liability alert
            $ihtLiability = (float) ($data['summary']['iht_liability'] ?? 0);
            if ($ihtLiability > 0) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Estate',
                    'severity' => $ihtLiability > 100000 ? 'critical' : 'important',
                    'title' => 'Inheritance Tax Liability',
                    'message' => sprintf(
                        'Your estate has a projected Inheritance Tax liability of %s. Consider mitigation strategies.',
                        number_format($ihtLiability, 0, '.', ',')
                    ),
                    'action_link' => '/estate',
                    'action_text' => 'Review Estate Plan',
                    'created_at' => now()->toISOString(),
                ];
            }

            // Will review alert
            $willStatus = $data['will_review_status'] ?? null;
            if ($willStatus === null || ! ($willStatus['has_will'] ?? false)) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Estate',
                    'severity' => 'important',
                    'title' => 'No Will Recorded',
                    'message' => 'No valid will has been recorded. Having an up-to-date will is essential for estate planning.',
                    'action_link' => '/estate',
                    'action_text' => 'Record Will Details',
                    'created_at' => now()->toISOString(),
                ];
            } elseif ($willStatus['is_stale'] ?? false) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Estate',
                    'severity' => 'info',
                    'title' => 'Will Review Overdue',
                    'message' => 'Your will has not been reviewed in over 3 years. Regular reviews ensure it reflects your current wishes.',
                    'action_link' => '/estate',
                    'action_text' => 'Review Will',
                    'created_at' => now()->toISOString(),
                ];
            }

            // Life policies not in trust alert
            $lifeCover = $data['life_cover'] ?? [];
            $policiesNotInTrust = (int) ($lifeCover['policies_not_in_trust_count'] ?? 0);
            if ($policiesNotInTrust > 0 && $ihtLiability > 0) {
                $alerts[] = [
                    'id' => $alertId++,
                    'module' => 'Estate',
                    'severity' => 'important',
                    'title' => 'Life Policies Outside Trust',
                    'message' => sprintf(
                        '%d life insurance %s not held in trust. Placing policies in trust can keep proceeds outside your estate for Inheritance Tax purposes.',
                        $policiesNotInTrust,
                        $policiesNotInTrust === 1 ? 'policy is' : 'policies are'
                    ),
                    'action_link' => '/estate',
                    'action_text' => 'Review Trust Arrangements',
                    'created_at' => now()->toISOString(),
                ];
            }

            return $alerts;
        } catch (\Throwable $e) {
            \Log::warning('Dashboard: Estate alerts failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
