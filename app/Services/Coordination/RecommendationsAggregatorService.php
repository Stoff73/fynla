<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\User;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\Investment\PortfolioAnalyzer;
use Illuminate\Support\Facades\Log;

class RecommendationsAggregatorService
{
    public function __construct(
        private readonly ProtectionAgent $protectionEngine,
        private readonly SavingsAgent $savingsCalculator,
        private readonly PortfolioAnalyzer $investmentAnalyzer,
        private readonly RetirementAgent $retirementAgent,
        private readonly ComprehensiveEstatePlanService $estatePlanService
    ) {}

    /**
     * Aggregate recommendations from all modules.
     */
    public function aggregateRecommendations(int $userId): array
    {
        $user = User::findOrFail($userId);
        $allRecommendations = [];

        // Protection module
        try {
            $protectionAnalysis = $this->protectionEngine->analyze($userId);
            $protectionRecs = $this->protectionEngine->generateRecommendations($protectionAnalysis);
            $formattedProtection = $this->formatRecommendations($protectionRecs, 'protection');
            $allRecommendations = array_merge($allRecommendations, $formattedProtection);
        } catch (\Exception $e) {
            Log::warning("Failed to get protection recommendations for user {$userId}: ".$e->getMessage());
        }

        // Savings module
        try {
            $savingsAnalysis = $this->savingsCalculator->analyze($userId);
            $savingsRecs = $savingsAnalysis['recommendations'] ?? [];
            $formattedSavings = $this->formatRecommendations($savingsRecs, 'savings');
            $allRecommendations = array_merge($allRecommendations, $formattedSavings);
        } catch (\Exception $e) {
            Log::warning("Failed to get savings recommendations for user {$userId}: ".$e->getMessage());
        }

        // Investment module
        try {
            $investmentAccounts = $user->investmentAccounts;
            if ($investmentAccounts->isNotEmpty()) {
                // Investment analyzer may not have recommendations structure yet
                // This is a safe guard for future implementation
                $investmentRecs = [];
                $formattedInvestment = $this->formatRecommendations($investmentRecs, 'investment');
                $allRecommendations = array_merge($allRecommendations, $formattedInvestment);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to get investment recommendations for user {$userId}: ".$e->getMessage());
        }

        // Retirement module
        try {
            $retirementAnalysis = $this->retirementAgent->analyze($userId);
            $retirementRecs = $retirementAnalysis['recommendations'] ?? [];
            $formattedRetirement = $this->formatRecommendations($retirementRecs, 'retirement');
            $allRecommendations = array_merge($allRecommendations, $formattedRetirement);
        } catch (\Exception $e) {
            Log::warning("Failed to get retirement recommendations for user {$userId}: ".$e->getMessage());
        }

        // Estate module
        try {
            $estatePlan = $this->estatePlanService->generateComprehensiveEstatePlan($user);
            $estateRecs = $estatePlan['recommendations'] ?? [];
            $formattedEstate = $this->formatRecommendations($estateRecs, 'estate');
            $allRecommendations = array_merge($allRecommendations, $formattedEstate);
        } catch (\Exception $e) {
            Log::warning("Failed to get estate recommendations for user {$userId}: ".$e->getMessage());
        }

        // Sort by priority score descending (highest priority first)
        usort($allRecommendations, function ($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return $allRecommendations;
    }

    /**
     * Format recommendations to ensure consistent structure.
     */
    private function formatRecommendations(array $recommendations, string $module): array
    {
        // Filter out non-array items (some analyzers may return booleans or other types)
        $validRecommendations = array_filter($recommendations, function ($rec) {
            return is_array($rec);
        });

        return array_map(function ($rec) use ($module) {
            return [
                'recommendation_id' => $rec['recommendation_id'] ?? $rec['id'] ?? uniqid("{$module}_"),
                'module' => $module,
                'recommendation_text' => $rec['recommendation_text'] ?? $rec['recommendation'] ?? $rec['text'] ?? '',
                'priority_score' => $rec['priority_score'] ?? $rec['priority'] ?? 50.0,
                'timeline' => $rec['timeline'] ?? $this->determineTimeline($rec['priority_score'] ?? 50.0),
                'category' => $rec['category'] ?? $this->determineCategory($rec, $module),
                'impact' => $rec['impact'] ?? $this->determineImpact($rec['priority_score'] ?? 50.0),
                'estimated_cost' => $rec['estimated_cost'] ?? $rec['cost'] ?? null,
                'potential_benefit' => $rec['potential_benefit'] ?? $rec['benefit'] ?? null,
                'status' => $rec['status'] ?? 'pending',
            ];
        }, $validRecommendations);
    }

    /**
     * Determine timeline based on priority score.
     */
    private function determineTimeline(float $priorityScore): string
    {
        if ($priorityScore >= 80) {
            return 'immediate';
        } elseif ($priorityScore >= 60) {
            return 'short_term';
        } elseif ($priorityScore >= 40) {
            return 'medium_term';
        } else {
            return 'long_term';
        }
    }

    /**
     * Determine category based on module and recommendation content.
     */
    private function determineCategory(array $rec, string $module): string
    {
        // Check if category is explicitly set
        if (isset($rec['category'])) {
            return $rec['category'];
        }

        // Determine category based on module
        return match ($module) {
            'protection' => 'risk_mitigation',
            'savings' => 'liquidity_management',
            'investment' => 'growth_optimization',
            'retirement' => 'retirement_planning',
            'estate' => 'tax_optimization',
            default => 'general',
        };
    }

    /**
     * Determine impact level based on priority score.
     */
    private function determineImpact(float $priorityScore): string
    {
        if ($priorityScore >= 70) {
            return 'high';
        } elseif ($priorityScore >= 40) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get recommendations filtered by module.
     */
    public function getRecommendationsByModule(int $userId, string $module): array
    {
        $allRecommendations = $this->aggregateRecommendations($userId);

        return array_filter($allRecommendations, function ($rec) use ($module) {
            return $rec['module'] === $module;
        });
    }

    /**
     * Get recommendations filtered by priority.
     */
    public function getRecommendationsByPriority(int $userId, string $priority): array
    {
        $allRecommendations = $this->aggregateRecommendations($userId);

        return array_filter($allRecommendations, function ($rec) use ($priority) {
            return $rec['impact'] === $priority;
        });
    }

    /**
     * Get recommendations filtered by timeline.
     */
    public function getRecommendationsByTimeline(int $userId, string $timeline): array
    {
        $allRecommendations = $this->aggregateRecommendations($userId);

        return array_filter($allRecommendations, function ($rec) use ($timeline) {
            return $rec['timeline'] === $timeline;
        });
    }

    /**
     * Get top N recommendations by priority.
     */
    public function getTopRecommendations(int $userId, int $limit = 5): array
    {
        $allRecommendations = $this->aggregateRecommendations($userId);

        return array_slice($allRecommendations, 0, $limit);
    }

    /**
     * Get summary statistics.
     */
    public function getSummary(int $userId): array
    {
        $allRecommendations = $this->aggregateRecommendations($userId);

        $summary = [
            'total_count' => count($allRecommendations),
            'by_priority' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'by_module' => [
                'protection' => 0,
                'savings' => 0,
                'investment' => 0,
                'retirement' => 0,
                'estate' => 0,
                'property' => 0,
            ],
            'by_timeline' => [
                'immediate' => 0,
                'short_term' => 0,
                'medium_term' => 0,
                'long_term' => 0,
            ],
            'total_potential_benefit' => 0,
            'total_estimated_cost' => 0,
        ];

        foreach ($allRecommendations as $rec) {
            // Count by priority
            $impact = $rec['impact'] ?? 'medium';
            $summary['by_priority'][$impact] = ($summary['by_priority'][$impact] ?? 0) + 1;

            // Count by module
            $module = $rec['module'] ?? 'general';
            if (isset($summary['by_module'][$module])) {
                $summary['by_module'][$module]++;
            }

            // Count by timeline
            $timeline = $rec['timeline'] ?? 'medium_term';
            $summary['by_timeline'][$timeline] = ($summary['by_timeline'][$timeline] ?? 0) + 1;

            // Sum potential benefits and costs
            if (isset($rec['potential_benefit'])) {
                $summary['total_potential_benefit'] += $rec['potential_benefit'];
            }
            if (isset($rec['estimated_cost'])) {
                $summary['total_estimated_cost'] += $rec['estimated_cost'];
            }
        }

        return $summary;
    }
}
