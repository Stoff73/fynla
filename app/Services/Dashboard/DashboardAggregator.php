<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

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
        private readonly RetirementAgent $retirementAgent
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
     * Calculate composite financial health score from all modules
     */
    public function calculateFinancialHealthScore(int $userId): array
    {
        try {
            // Get individual scores
            $protectionScore = $this->getProtectionScore($userId);
            $emergencyFundScore = $this->getEmergencyFundScore($userId);
            $retirementScore = $this->getRetirementScore($userId);
            $investmentScore = $this->getInvestmentScore($userId);
            $estateScore = $this->getEstateScore($userId);

            // Calculate weighted average
            $compositeScore = (
                ($protectionScore * 0.20) +
                ($emergencyFundScore * 0.15) +
                ($retirementScore * 0.25) +
                ($investmentScore * 0.20) +
                ($estateScore * 0.20)
            );

            return [
                'composite_score' => round($compositeScore, 2),
                'breakdown' => [
                    'protection' => [
                        'score' => $protectionScore,
                        'weight' => 0.20,
                        'contribution' => round($protectionScore * 0.20, 2),
                    ],
                    'emergency_fund' => [
                        'score' => $emergencyFundScore,
                        'weight' => 0.15,
                        'contribution' => round($emergencyFundScore * 0.15, 2),
                    ],
                    'retirement' => [
                        'score' => $retirementScore,
                        'weight' => 0.25,
                        'contribution' => round($retirementScore * 0.25, 2),
                    ],
                    'investment' => [
                        'score' => $investmentScore,
                        'weight' => 0.20,
                        'contribution' => round($investmentScore * 0.20, 2),
                    ],
                    'estate' => [
                        'score' => $estateScore,
                        'weight' => 0.20,
                        'contribution' => round($estateScore * 0.20, 2),
                    ],
                ],
                'label' => $this->getHealthLabel($compositeScore),
                'recommendation' => $this->getHealthRecommendation($compositeScore),
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to calculate financial health score: '.$e->getMessage());

            return [
                'composite_score' => 0,
                'breakdown' => [],
                'label' => 'Unknown',
                'recommendation' => 'Unable to calculate financial health score',
            ];
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

    // Private helper methods for each module

    private function getProtectionSummary(int $userId): array
    {
        // In real implementation, would call ProtectionAgent
        return [
            'adequacy_score' => 75,
            'total_coverage' => 500000,
            'premium_total' => 2400,
            'critical_gaps' => 1,
        ];
    }

    private function getSavingsSummary(int $userId): array
    {
        // In real implementation, would call SavingsAgent
        return [
            'emergency_fund_runway' => 4,
            'total_savings' => 25000,
            'isa_usage_percent' => 40,
            'goals_on_track' => 2,
        ];
    }

    private function getInvestmentSummary(int $userId): array
    {
        // In real implementation, would call InvestmentAgent
        return [
            'portfolio_value' => 150000,
            'ytd_return' => 8.5,
            'holdings_count' => 12,
            'needs_rebalancing' => false,
        ];
    }

    private function getRetirementSummary(int $userId): array
    {
        // In real implementation, would call RetirementAgent
        return [
            'projected_income' => 35000,
            'target_income' => 40000,
            'income_gap' => 5000,
            'years_to_retirement' => 15,
        ];
    }

    private function getEstateSummary(int $userId): array
    {
        // In real implementation, would call EstateAgent
        return [
            'net_worth' => 675000,
            'iht_liability' => 0,
            'probate_readiness' => 85,
        ];
    }

    private function getProtectionScore(int $userId): float
    {
        // In real implementation, would fetch from ProtectionAgent
        return 75.0;
    }

    private function getEmergencyFundScore(int $userId): float
    {
        // In real implementation, calculate from emergency fund runway
        // Target: 6 months = 100%
        $runway = 4; // months

        return min(100, ($runway / 6) * 100);
    }

    private function getRetirementScore(int $userId): float
    {
        // In real implementation, would fetch from RetirementAgent
        return 68.0;
    }

    private function getInvestmentScore(int $userId): float
    {
        // In real implementation, would fetch diversification score from InvestmentAgent
        return 80.0;
    }

    private function getEstateScore(int $userId): float
    {
        // In real implementation, would fetch probate readiness from EstateAgent
        return 85.0;
    }

    private function getProtectionAlerts(int $userId): array
    {
        return [
            [
                'id' => 1,
                'module' => 'Protection',
                'severity' => 'important',
                'title' => 'Income Protection Gap',
                'message' => 'Consider adding income protection to cover monthly expenses',
                'action_link' => '/protection',
                'action_text' => 'Review Coverage',
                'created_at' => now()->toISOString(),
            ],
        ];
    }

    private function getSavingsAlerts(int $userId): array
    {
        return [
            [
                'id' => 2,
                'module' => 'Savings',
                'severity' => 'critical',
                'title' => 'Emergency Fund Below Target',
                'message' => 'Your emergency fund covers only 4 months. Target is 6 months.',
                'action_link' => '/savings',
                'action_text' => 'Add to Emergency Fund',
                'created_at' => now()->toISOString(),
            ],
        ];
    }

    private function getInvestmentAlerts(int $userId): array
    {
        return [
            [
                'id' => 3,
                'module' => 'Investment',
                'severity' => 'info',
                'title' => 'Portfolio Performing Well',
                'message' => 'Your portfolio is up 8.5% YTD and well diversified',
                'action_link' => '/investment',
                'action_text' => 'View Details',
                'created_at' => now()->toISOString(),
            ],
        ];
    }

    private function getRetirementAlerts(int $userId): array
    {
        return [
            [
                'id' => 4,
                'module' => 'Retirement',
                'severity' => 'important',
                'title' => 'Pension Contribution Opportunity',
                'message' => 'You have unused annual allowance of £15,000',
                'action_link' => '/retirement',
                'action_text' => 'Optimize Contributions',
                'created_at' => now()->toISOString(),
            ],
        ];
    }

    private function getEstateAlerts(int $userId): array
    {
        return [];
    }

    private function getHealthLabel(float $score): string
    {
        if ($score >= 80) {
            return 'Excellent';
        }
        if ($score >= 60) {
            return 'Good';
        }
        if ($score >= 40) {
            return 'Fair';
        }

        return 'Needs Improvement';
    }

    private function getHealthRecommendation(float $score): string
    {
        if ($score >= 80) {
            return 'Your finances are in great shape. Keep up the good work!';
        } elseif ($score >= 60) {
            return 'Your finances are on track with some room for improvement.';
        } else {
            return 'Consider addressing key areas to improve your financial health.';
        }
    }
}
