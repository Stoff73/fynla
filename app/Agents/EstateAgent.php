<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\User;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\GiftingStrategyOptimizer;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\PersonalizedTrustStrategyService;
use Illuminate\Support\Facades\Cache;

/**
 * EstateAgent orchestrates estate planning analysis and recommendations.
 *
 * Coordinates between IHT calculations, gifting strategies, trust recommendations,
 * and comprehensive estate planning services.
 */
class EstateAgent extends BaseAgent
{
    public function __construct(
        private IHTCalculationService $ihtCalculator,
        private EstateAssetAggregatorService $assetAggregator,
        private ComprehensiveEstatePlanService $estatePlanService,
        private GiftingStrategyOptimizer $giftingOptimizer,
        private PersonalizedTrustStrategyService $trustStrategyService
    ) {}

    /**
     * Analyze user's estate planning situation.
     */
    public function analyze(int $userId): array
    {
        $cacheKey = "estate_analysis_{$userId}";
        $cacheTags = ['estate', 'user_'.$userId];

        return $this->remember($cacheKey, function () use ($userId) {
            $user = User::with([
                'ihtProfile',
                'assets',
                'properties',
                'liabilities',
                'mortgages',
                'spouse',
                'familyMembers',
                'trusts',
                'gifts',
            ])->findOrFail($userId);

            // Aggregate all estate assets
            $assetSummary = $this->assetAggregator->aggregateEstateAssets($user);

            // Calculate IHT if profile exists
            $ihtCalculation = null;
            $ihtLiability = 0;
            $effectiveTaxRate = 0;

            if ($user->ihtProfile) {
                try {
                    $ihtCalculation = $this->ihtCalculator->calculateIHT($user);
                    $ihtLiability = $ihtCalculation['iht_liability'] ?? 0;
                    $grossEstate = $ihtCalculation['gross_estate'] ?? 1;
                    $effectiveTaxRate = $grossEstate > 0 ? ($ihtLiability / $grossEstate) * 100 : 0;
                } catch (\Exception $e) {
                    // Continue without IHT calculation
                }
            }

            // Calculate estate health score (0-100)
            $healthScore = $this->calculateEstateHealthScore($user, $assetSummary, $ihtLiability);

            // Get trust recommendations
            $trustRecommendations = [];
            try {
                $trustRecommendations = $this->trustStrategyService->getPersonalizedStrategies($user);
            } catch (\Exception $e) {
                // Continue without trust recommendations
            }

            // Get gifting opportunities
            $giftingOpportunities = [];
            try {
                $giftingOpportunities = $this->giftingOptimizer->identifyOpportunities($user);
            } catch (\Exception $e) {
                // Continue without gifting opportunities
            }

            // Calculate current age and life expectancy context
            $currentAge = $user->date_of_birth ?
                (int) $user->date_of_birth->diffInYears(now()) : 50;

            return $this->response(
                true,
                'Estate analysis completed successfully.',
                [
                    'summary' => [
                        'gross_estate' => $assetSummary['gross_estate'] ?? 0,
                        'net_estate' => $assetSummary['net_estate'] ?? 0,
                        'total_liabilities' => $assetSummary['total_liabilities'] ?? 0,
                        'iht_liability' => $ihtLiability,
                        'effective_tax_rate' => round($effectiveTaxRate, 2),
                        'health_score' => $healthScore,
                    ],
                    'asset_breakdown' => $assetSummary['breakdown'] ?? [],
                    'iht_calculation' => $ihtCalculation,
                    'trust_recommendations' => $trustRecommendations,
                    'gifting_opportunities' => $giftingOpportunities,
                    'profile' => [
                        'current_age' => $currentAge,
                        'marital_status' => $user->marital_status,
                        'has_dependents' => ($user->familyMembers()->where('relationship', 'child')->count() > 0),
                        'has_spouse' => $user->spouse !== null,
                        'has_iht_profile' => $user->ihtProfile !== null,
                    ],
                ]
            );
        }, null, $cacheTags);
    }

    /**
     * Generate personalized recommendations based on analysis.
     */
    public function generateRecommendations(array $analysisData): array
    {
        if (! isset($analysisData['data'])) {
            return $this->response(
                false,
                'Analysis data is incomplete. Please run analysis first.',
                []
            );
        }

        $recommendations = [];
        $data = $analysisData['data'];
        $ihtLiability = $data['summary']['iht_liability'] ?? 0;
        $healthScore = $data['summary']['health_score'] ?? 0;

        // High IHT liability recommendations
        if ($ihtLiability > 100000) {
            $recommendations[] = [
                'category' => 'iht_mitigation',
                'priority' => 'high',
                'title' => 'Significant IHT Liability Identified',
                'description' => "Your estimated IHT liability of {$this->formatCurrency($ihtLiability)} could be reduced through strategic planning.",
                'actions' => [
                    'Review trust structures',
                    'Consider lifetime gifting',
                    'Explore business relief opportunities',
                ],
            ];
        }

        // Trust recommendations
        if (! empty($data['trust_recommendations'])) {
            $recommendations[] = [
                'category' => 'trusts',
                'priority' => 'medium',
                'title' => 'Trust Planning Opportunities',
                'description' => 'Based on your circumstances, certain trust arrangements may benefit your estate planning.',
                'actions' => array_slice(array_column($data['trust_recommendations'], 'recommendation'), 0, 3),
            ];
        }

        // Gifting recommendations
        if (! empty($data['gifting_opportunities'])) {
            $recommendations[] = [
                'category' => 'gifting',
                'priority' => 'medium',
                'title' => 'Gifting Strategy Opportunities',
                'description' => 'Regular gifting can reduce your estate value over time.',
                'actions' => [
                    'Use annual £3,000 exemption',
                    'Consider gifts out of normal expenditure',
                    'Review PET (Potentially Exempt Transfer) opportunities',
                ],
            ];
        }

        // Low health score recommendations
        if ($healthScore < 50) {
            $recommendations[] = [
                'category' => 'planning',
                'priority' => 'high',
                'title' => 'Estate Planning Needs Attention',
                'description' => 'Your estate planning score indicates room for improvement.',
                'actions' => [
                    'Complete your IHT profile',
                    'Review beneficiary designations',
                    'Consider writing or updating your will',
                ],
            ];
        }

        return $this->response(
            true,
            'Recommendations generated successfully.',
            [
                'recommendations' => $recommendations,
                'health_score' => $healthScore,
            ]
        );
    }

    /**
     * Build what-if scenarios for estate planning.
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $user = User::with([
            'ihtProfile',
            'assets',
            'properties',
            'liabilities',
            'spouse',
        ])->findOrFail($userId);

        $scenarios = [];
        $scenarioTypes = $parameters['scenario_types'] ?? ['current', 'optimized', 'gifting'];

        foreach ($scenarioTypes as $scenarioType) {
            $scenarios[$scenarioType] = match ($scenarioType) {
                'current' => $this->buildCurrentScenario($user),
                'optimized' => $this->buildOptimizedScenario($user, $parameters),
                'gifting' => $this->buildGiftingScenario($user, $parameters),
                'property_downsizing' => $this->buildDownsizingScenario($user, $parameters),
                'trust_creation' => $this->buildTrustScenario($user, $parameters),
                default => null,
            };
        }

        return $this->response(
            true,
            'Scenarios built successfully.',
            [
                'scenarios' => array_filter($scenarios),
            ]
        );
    }

    /**
     * Calculate estate health score (0-100).
     */
    private function calculateEstateHealthScore(User $user, array $assetSummary, float $ihtLiability): int
    {
        $score = 100;

        // Deduct for missing IHT profile
        if (! $user->ihtProfile) {
            $score -= 20;
        }

        // Deduct for high IHT liability relative to estate
        $grossEstate = $assetSummary['gross_estate'] ?? 0;
        if ($grossEstate > 0) {
            $ihtRatio = $ihtLiability / $grossEstate;
            if ($ihtRatio > 0.3) {
                $score -= 25;
            } elseif ($ihtRatio > 0.2) {
                $score -= 15;
            } elseif ($ihtRatio > 0.1) {
                $score -= 10;
            }
        }

        // Deduct for no trusts when estate is significant
        if ($grossEstate > 650000 && $user->trusts()->count() === 0) {
            $score -= 10;
        }

        // Deduct for no spouse allowance utilization potential
        if ($user->marital_status === 'married' && ! $user->spouse) {
            $score -= 5;
        }

        // Deduct for high liquidity risk
        $liquidAssets = $assetSummary['breakdown']['liquid'] ?? 0;
        if ($ihtLiability > 0 && $liquidAssets < $ihtLiability * 0.5) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    /**
     * Build current state scenario.
     */
    private function buildCurrentScenario(User $user): array
    {
        $assetSummary = $this->assetAggregator->aggregateEstateAssets($user);

        $ihtLiability = 0;
        if ($user->ihtProfile) {
            try {
                $result = $this->ihtCalculator->calculateIHT($user);
                $ihtLiability = $result['iht_liability'] ?? 0;
            } catch (\Exception $e) {
                // Continue with zero
            }
        }

        return [
            'name' => 'Current Estate Position',
            'gross_estate' => $assetSummary['gross_estate'] ?? 0,
            'net_estate' => $assetSummary['net_estate'] ?? 0,
            'iht_liability' => $ihtLiability,
            'to_beneficiaries' => ($assetSummary['net_estate'] ?? 0) - $ihtLiability,
        ];
    }

    /**
     * Build optimized scenario with all strategies applied.
     */
    private function buildOptimizedScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);

        // Estimate savings from various strategies
        $giftingSavings = min($current['iht_liability'] * 0.15, 50000);
        $trustSavings = min($current['iht_liability'] * 0.1, 40000);

        $optimizedIHT = max(0, $current['iht_liability'] - $giftingSavings - $trustSavings);

        return [
            'name' => 'Optimized Estate Plan',
            'gross_estate' => $current['gross_estate'],
            'net_estate' => $current['net_estate'],
            'iht_liability' => $optimizedIHT,
            'to_beneficiaries' => $current['net_estate'] - $optimizedIHT,
            'estimated_savings' => $current['iht_liability'] - $optimizedIHT,
            'strategies_applied' => ['gifting', 'trusts', 'allowance_optimization'],
        ];
    }

    /**
     * Build gifting strategy scenario.
     */
    private function buildGiftingScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);
        $yearsOfGifting = $parameters['gifting_years'] ?? 7;
        $annualGiftAmount = $parameters['annual_gift'] ?? 3000;

        $totalGifted = $annualGiftAmount * $yearsOfGifting;
        $ihtSaved = $totalGifted * 0.4; // 40% IHT rate

        return [
            'name' => "Gifting Strategy ({$yearsOfGifting} years)",
            'gross_estate' => $current['gross_estate'] - $totalGifted,
            'net_estate' => $current['net_estate'] - $totalGifted,
            'iht_liability' => max(0, $current['iht_liability'] - $ihtSaved),
            'to_beneficiaries' => $current['net_estate'] - max(0, $current['iht_liability'] - $ihtSaved),
            'total_gifted' => $totalGifted,
            'estimated_iht_saved' => $ihtSaved,
        ];
    }

    /**
     * Build property downsizing scenario.
     */
    private function buildDownsizingScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);
        $equityRelease = $parameters['equity_release'] ?? 200000;

        return [
            'name' => 'Property Downsizing',
            'gross_estate' => $current['gross_estate'] - $equityRelease,
            'net_estate' => $current['net_estate'] - $equityRelease,
            'iht_liability' => max(0, $current['iht_liability'] - ($equityRelease * 0.4)),
            'to_beneficiaries' => $current['net_estate'] - $equityRelease - max(0, $current['iht_liability'] - ($equityRelease * 0.4)),
            'cash_released' => $equityRelease,
        ];
    }

    /**
     * Build trust creation scenario.
     */
    private function buildTrustScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);
        $trustValue = $parameters['trust_value'] ?? 325000;

        // Discretionary trust within NRB
        $ihtReduction = min($trustValue * 0.4, $current['iht_liability']);

        return [
            'name' => 'Trust Creation Strategy',
            'gross_estate' => $current['gross_estate'],
            'net_estate' => $current['net_estate'],
            'iht_liability' => max(0, $current['iht_liability'] - $ihtReduction),
            'to_beneficiaries' => $current['net_estate'] - max(0, $current['iht_liability'] - $ihtReduction),
            'trust_value' => $trustValue,
            'estimated_iht_saved' => $ihtReduction,
        ];
    }

    /**
     * Invalidate cache for user's estate analysis.
     */
    public function invalidateCache(int $userId): void
    {
        Cache::tags(['estate', 'user_'.$userId])->flush();
    }
}
