<?php

declare(strict_types=1);

namespace App\Services\Coordination;

/**
 * PriorityRanker
 *
 * Ranks and prioritizes recommendations from all modules based on:
 * - Urgency (how critical is this action?)
 * - Impact (what's the financial benefit?)
 * - Effort (how easy is it to implement?)
 * - User priorities (user-specified goals)
 */
class PriorityRanker
{
    /**
     * Rank all recommendations based on priority scoring
     *
     * @param  array  $allRecommendations  Recommendations from all modules
     * @param  array  $userContext  User profile and preferences
     * @return array Ranked recommendations with scores
     */
    public function rankRecommendations(array $allRecommendations, array $userContext): array
    {
        $scoredRecommendations = [];

        foreach ($allRecommendations as $module => $recommendations) {
            if ($module === 'module_scores' || $module === 'available_surplus') {
                continue;
            }

            if (! is_array($recommendations)) {
                continue;
            }

            foreach ($recommendations as $recommendation) {
                $score = $this->calculateRecommendationScore($recommendation, $module, $userContext);

                $scoredRecommendations[] = array_merge($recommendation, [
                    'module' => $module,
                    'priority_score' => $score['total_score'],
                    'urgency_score' => $score['urgency'],
                    'impact_score' => $score['impact'],
                    'ease_score' => $score['ease'],
                    'user_priority_score' => $score['user_priority'],
                    'timeline' => $this->determineTimeline($score['urgency']),
                ]);
            }
        }

        // Sort by priority score descending
        usort($scoredRecommendations, fn ($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        return $scoredRecommendations;
    }

    /**
     * Calculate comprehensive priority score for a recommendation
     *
     * Formula: score = (urgency × 0.4) + (impact × 0.3) + (ease × 0.2) + (userPriority × 0.1)
     *
     * @param  array  $recommendation  Single recommendation
     * @param  string  $module  Module name
     * @param  array  $userContext  User preferences
     * @return array Detailed scores
     */
    public function calculateRecommendationScore(array $recommendation, string $module, array $userContext): array
    {
        $urgency = $this->calculateUrgencyScore($recommendation, $module);
        $impact = $this->calculateImpactScore($recommendation, $module);
        $ease = $this->calculateEaseScore($recommendation, $module);
        $userPriority = $this->calculateUserPriorityScore($module, $userContext);

        $totalScore = ($urgency * 0.4) + ($impact * 0.3) + ($ease * 0.2) + ($userPriority * 0.1);

        return [
            'total_score' => round($totalScore, 2),
            'urgency' => round($urgency, 2),
            'impact' => round($impact, 2),
            'ease' => round($ease, 2),
            'user_priority' => round($userPriority, 2),
        ];
    }

    /**
     * Group recommendations by category (module)
     *
     * @param  array  $recommendations  Scored recommendations
     * @return array Grouped by module
     */
    public function groupByCategory(array $recommendations): array
    {
        $grouped = [
            'protection' => [],
            'savings' => [],
            'investment' => [],
            'retirement' => [],
            'estate' => [],
            'goals' => [],
        ];

        foreach ($recommendations as $rec) {
            $module = $rec['module'] ?? 'other';
            if (isset($grouped[$module])) {
                $grouped[$module][] = $rec;
            }
        }

        return $grouped;
    }

    /**
     * Create action plan with timeline grouping
     *
     * @param  array  $rankedRecommendations  Ranked recommendations
     * @return array Action plan grouped by timeline
     */
    public function createActionPlan(array $rankedRecommendations): array
    {
        $plan = [
            'immediate' => [], // Urgency >= 80, do within 1 month
            'short_term' => [], // Urgency 60-79, do within 3 months
            'medium_term' => [], // Urgency 40-59, do within 12 months
            'long_term' => [], // Urgency < 40, do within 12+ months
        ];

        foreach ($rankedRecommendations as $rec) {
            $timeline = $rec['timeline'] ?? $this->determineTimeline($rec['urgency_score'] ?? 50);
            $plan[$timeline][] = $rec;
        }

        return [
            'action_plan' => $plan,
            'summary' => [
                'immediate_actions' => count($plan['immediate']),
                'short_term_actions' => count($plan['short_term']),
                'medium_term_actions' => count($plan['medium_term']),
                'long_term_actions' => count($plan['long_term']),
                'total_actions' => count($rankedRecommendations),
            ],
        ];
    }

    /**
     * Calculate urgency score (0-100)
     *
     * Factors:
     * - Critical gaps (e.g., no life insurance with dependents) = high urgency
     * - Adequacy scores < 50 = high urgency
     * - Time-sensitive opportunities (e.g., tax year end) = high urgency
     */
    private function calculateUrgencyScore(array $recommendation, string $module): float
    {
        $urgency = 50; // Default medium urgency

        // Module-specific urgency scoring
        switch ($module) {
            case 'protection':
                // Life insurance gap with dependents = critical
                if (isset($recommendation['coverage_gap']) && $recommendation['coverage_gap'] > 100000) {
                    $urgency = 95;
                } elseif (isset($recommendation['adequacy_score']) && $recommendation['adequacy_score'] < 30) {
                    $urgency = 90;
                } elseif (isset($recommendation['adequacy_score']) && $recommendation['adequacy_score'] < 50) {
                    $urgency = 75;
                } elseif (isset($recommendation['adequacy_score']) && $recommendation['adequacy_score'] < 70) {
                    $urgency = 60;
                } else {
                    $urgency = 40;
                }
                break;

            case 'savings':
                // Emergency fund < 3 months = critical
                if (isset($recommendation['emergency_fund_months']) && $recommendation['emergency_fund_months'] < 1) {
                    $urgency = 95;
                } elseif (isset($recommendation['emergency_fund_months']) && $recommendation['emergency_fund_months'] < 3) {
                    $urgency = 85;
                } elseif (isset($recommendation['emergency_fund_months']) && $recommendation['emergency_fund_months'] < 6) {
                    $urgency = 65;
                } else {
                    $urgency = 45;
                }
                break;

            case 'retirement':
                // Retirement urgency based on income gap
                $incomeGap = $recommendation['income_gap'] ?? 0;
                if ($incomeGap > 15000) {
                    $urgency = 80;
                } elseif ($incomeGap > 10000) {
                    $urgency = 70;
                } elseif ($incomeGap > 5000) {
                    $urgency = 55;
                } else {
                    $urgency = 35;
                }

                // Increase urgency if close to retirement
                if (isset($recommendation['years_to_retirement']) && $recommendation['years_to_retirement'] < 10) {
                    $urgency = min(100, $urgency + 20);
                }
                break;

            case 'investment':
                // Goal-based urgency
                if (isset($recommendation['goal_probability']) && $recommendation['goal_probability'] < 30) {
                    $urgency = 75;
                } elseif (isset($recommendation['goal_probability']) && $recommendation['goal_probability'] < 50) {
                    $urgency = 60;
                } else {
                    $urgency = 40;
                }

                // Time-sensitive goal increases urgency
                if (isset($recommendation['years_to_goal']) && $recommendation['years_to_goal'] < 3) {
                    $urgency = min(100, $urgency + 25);
                }
                break;

            case 'estate':
                // IHT liability
                if (isset($recommendation['iht_liability']) && $recommendation['iht_liability'] > 500000) {
                    $urgency = 85;
                } elseif (isset($recommendation['iht_liability']) && $recommendation['iht_liability'] > 200000) {
                    $urgency = 70;
                } elseif (isset($recommendation['iht_liability']) && $recommendation['iht_liability'] > 50000) {
                    $urgency = 55;
                } else {
                    $urgency = 30;
                }

                // Increase urgency for older age
                if (isset($recommendation['age']) && $recommendation['age'] > 70) {
                    $urgency = min(100, $urgency + 15);
                }
                break;

            case 'goals':
                // Goal urgency based on category and status
                $category = $recommendation['category'] ?? '';
                if ($category === 'Progress' || $category === 'Affordability') {
                    $urgency = 65; // Behind schedule or overcommitted
                } elseif ($category === 'Safety Net') {
                    $urgency = 75; // No emergency fund goal
                } elseif ($category === 'Getting Started') {
                    $urgency = 50; // No goals set yet
                } else {
                    $urgency = 45;
                }
                break;
        }

        return min(100, max(0, $urgency));
    }

    /**
     * Calculate impact score (0-100)
     *
     * Financial benefit or risk reduction value
     */
    private function calculateImpactScore(array $recommendation, string $module): float
    {
        $impact = 50; // Default medium impact

        switch ($module) {
            case 'protection':
                // Coverage gap value
                if (isset($recommendation['coverage_gap'])) {
                    $gap = $recommendation['coverage_gap'];
                    if ($gap > 500000) {
                        $impact = 95;
                    } elseif ($gap > 250000) {
                        $impact = 85;
                    } elseif ($gap > 100000) {
                        $impact = 70;
                    } else {
                        $impact = 55;
                    }
                }
                break;

            case 'savings':
                // Emergency fund shortfall
                if (isset($recommendation['emergency_fund_shortfall'])) {
                    $shortfall = $recommendation['emergency_fund_shortfall'];
                    if ($shortfall > 20000) {
                        $impact = 90;
                    } elseif ($shortfall > 10000) {
                        $impact = 75;
                    } elseif ($shortfall > 5000) {
                        $impact = 60;
                    } else {
                        $impact = 45;
                    }
                }
                break;

            case 'retirement':
                // Income gap in retirement
                if (isset($recommendation['income_gap'])) {
                    $gap = $recommendation['income_gap'];
                    if ($gap > 30000) {
                        $impact = 95;
                    } elseif ($gap > 15000) {
                        $impact = 80;
                    } elseif ($gap > 5000) {
                        $impact = 65;
                    } else {
                        $impact = 50;
                    }
                }
                break;

            case 'investment':
                // Expected return increase
                if (isset($recommendation['expected_benefit'])) {
                    $benefit = $recommendation['expected_benefit'];
                    if ($benefit > 50000) {
                        $impact = 90;
                    } elseif ($benefit > 20000) {
                        $impact = 75;
                    } elseif ($benefit > 10000) {
                        $impact = 60;
                    } else {
                        $impact = 45;
                    }
                }
                break;

            case 'estate':
                // IHT saving
                if (isset($recommendation['iht_saving'])) {
                    $saving = $recommendation['iht_saving'];
                    if ($saving > 200000) {
                        $impact = 95;
                    } elseif ($saving > 100000) {
                        $impact = 85;
                    } elseif ($saving > 50000) {
                        $impact = 70;
                    } else {
                        $impact = 55;
                    }
                }
                break;

            case 'goals':
                // Goal impact based on category
                $category = $recommendation['category'] ?? '';
                if ($category === 'Affordability') {
                    $impact = 70; // Overcommitted impacts all goals
                } elseif ($category === 'Safety Net') {
                    $impact = 75; // Emergency fund is foundational
                } elseif ($category === 'Progress') {
                    $impact = 60; // Goals behind schedule
                } else {
                    $impact = 45;
                }
                break;
        }

        return min(100, max(0, $impact));
    }

    /**
     * Calculate ease of implementation score (0-100)
     *
     * Higher score = easier to implement
     */
    private function calculateEaseScore(array $recommendation, string $module): float
    {
        $ease = 50; // Default medium ease

        // Check for cost requirement
        if (isset($recommendation['recommended_monthly_contribution']) || isset($recommendation['recommended_monthly_premium'])) {
            $monthlyCost = $recommendation['recommended_monthly_contribution'] ?? $recommendation['recommended_monthly_premium'] ?? 0;

            if ($monthlyCost === 0) {
                $ease = 90; // No cost = very easy
            } elseif ($monthlyCost < 50) {
                $ease = 80; // Low cost
            } elseif ($monthlyCost < 200) {
                $ease = 65; // Moderate cost
            } elseif ($monthlyCost < 500) {
                $ease = 45; // Higher cost
            } else {
                $ease = 30; // Significant cost
            }
        }

        // Module-specific ease adjustments
        switch ($module) {
            case 'protection':
                // Buying insurance = moderate effort (application, underwriting)
                $ease = min($ease, 60);
                break;

            case 'savings':
                // Opening savings account = easy
                $ease = max($ease, 70);
                break;

            case 'investment':
                // Opening investment account = moderate effort
                $ease = min($ease, 65);
                break;

            case 'retirement':
                // Pension changes = easy if workplace pension, harder if personal
                if (isset($recommendation['pension_type']) && $recommendation['pension_type'] === 'workplace') {
                    $ease = max($ease, 75);
                } else {
                    $ease = min($ease, 55);
                }
                break;

            case 'estate':
                // Estate planning = moderate to difficult (legal docs, trusts)
                if (isset($recommendation['action_type']) && $recommendation['action_type'] === 'will') {
                    $ease = 50; // Will writing = moderate effort
                } elseif (isset($recommendation['action_type']) && $recommendation['action_type'] === 'trust') {
                    $ease = 30; // Trust setup = complex
                } else {
                    $ease = 60;
                }
                break;

            case 'goals':
                // Goal actions are generally actionable
                $ease = 70; // Adjusting contributions/timelines is straightforward
                break;
        }

        return min(100, max(0, $ease));
    }

    /**
     * Calculate user priority score based on stated preferences
     */
    private function calculateUserPriorityScore(string $module, array $userContext): float
    {
        $priorities = $userContext['module_priorities'] ?? [];

        // Default priorities if not set
        $defaultPriorities = [
            'protection' => 70,
            'savings' => 75,
            'retirement' => 65,
            'investment' => 60,
            'estate' => 50,
            'goals' => 55,
        ];

        return $priorities[$module] ?? $defaultPriorities[$module] ?? 50;
    }

    /**
     * Determine timeline based on urgency score
     */
    private function determineTimeline(float $urgencyScore): string
    {
        if ($urgencyScore >= 80) {
            return 'immediate'; // Within 1 month
        } elseif ($urgencyScore >= 60) {
            return 'short_term'; // Within 3 months
        } elseif ($urgencyScore >= 40) {
            return 'medium_term'; // Within 12 months
        } else {
            return 'long_term'; // 12+ months
        }
    }
}
