<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\User;
use App\Services\Coordination\PlanSources\EstateStrategySource;
use App\Services\Coordination\PlanSources\InvestmentStrategySource;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\PlanSources\ProtectionStrategySource;
use App\Services\Coordination\PlanSources\RetirementStrategySource;
use App\Services\Coordination\PlanSources\SavingsStrategySource;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\PrerequisiteGateService;
use Illuminate\Support\Facades\Log;

class RecommendationsAggregatorService
{
    public function __construct(
        private readonly ProtectionAgent $protectionEngine,
        private readonly SavingsAgent $savingsCalculator,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly ComprehensiveEstatePlanService $estatePlanService,
        private readonly GoalsAgent $goalsAgent,
        private readonly RecommendationPersonaliser $personaliser,
        private readonly PrerequisiteGateService $gate,
        private readonly ComposedTaxPlanService $taxPlan,
    ) {}

    /**
     * Aggregate recommendations from all modules.
     */
    public function aggregateRecommendations(int $userId): array
    {
        $user = User::findOrFail($userId);
        $allRecommendations = [];

        // When enabled, the five non-tax modules are sourced from the generalised
        // ComposedModulePlanService (catalogue-annotated, sequencing-aware) instead
        // of their raw per-agent blocks below; the raw blocks remain as the rollback
        // path (flag off). Each composed module is gated identically to its raw block,
        // and locked strategies are excluded — dashboards list actionable
        // recommendations; unlock prompts are surfaced via Fyn pointers / the
        // holistic plan, mirroring how the tax block already behaves.
        $composedEnabled = (bool) config('coordination.composed_module_plans', true);

        if ($composedEnabled) {
            $composedSources = [
                'protection' => ProtectionStrategySource::class,
                'savings' => SavingsStrategySource::class,
                'retirement' => RetirementStrategySource::class,
                'investment' => InvestmentStrategySource::class,
                'estate' => EstateStrategySource::class,
            ];
            foreach ($composedSources as $module => $sourceClass) {
                if (! $this->moduleGateOpen($module, $user)) {
                    continue;
                }
                try {
                    $allRecommendations = array_merge(
                        $allRecommendations,
                        $this->composedModuleRecs($module, app($sourceClass), $user)
                    );
                } catch (\Exception $e) {
                    Log::warning("Failed to get composed {$module} recommendations for user {$userId}: ".$e->getMessage());
                }
            }
        }

        // Protection module
        if (! $composedEnabled && $this->moduleGateOpen('protection', $user)) {
            try {
                $protectionAnalysis = $this->protectionEngine->analyze($userId);
                $rawRecs = $protectionAnalysis['data']['recommendations'] ?? [];
                // Protection recs use a 'title'/'action' key (not 'recommendation_text')
                // and a 1-5 'priority' (not 'priority_score') — map them like the other
                // modules, passing through any explicit fields untouched so
                // formatRecommendations keeps its normalisation contract.
                $protectionRecs = array_map(static function (array $r): array {
                    return [
                        'recommendation_id' => $r['recommendation_id'] ?? $r['id'] ?? null,
                        'recommendation_text' => $r['title'] ?? $r['action'] ?? $r['description'] ?? $r['recommendation_text'] ?? '',
                        'priority_score' => isset($r['priority_score'])
                            ? (float) $r['priority_score']
                            : (isset($r['priority']) ? max(40, 90 - ((int) $r['priority'] * 5)) : 70),
                        'category' => $r['category'] ?? null,
                        'estimated_cost' => $r['estimated_cost'] ?? null,
                        'potential_benefit' => $r['potential_benefit'] ?? null,
                    ];
                }, is_array($rawRecs) ? $rawRecs : []);
                // Also extract coverage gaps as recommendations.
                $gaps = $protectionAnalysis['data']['gaps'] ?? [];
                foreach ((is_array($gaps) ? $gaps : []) as $gap) {
                    if (is_array($gap) && ! empty($gap['recommendation'])) {
                        $protectionRecs[] = [
                            'recommendation_text' => $gap['recommendation'],
                            'priority_score' => 70,
                            'category' => $gap['type'] ?? 'coverage_gap',
                        ];
                    }
                }
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($protectionRecs, 'protection'));
            } catch (\Exception $e) {
                Log::warning("Failed to get protection recommendations for user {$userId}: ".$e->getMessage());
            }
        }

        // Savings module
        if (! $composedEnabled && $this->moduleGateOpen('savings', $user)) {
            try {
                $savingsAnalysis = $this->savingsCalculator->analyze($userId);
                $savingsRecs = [];
                // Emergency fund recommendation
                $ef = $savingsAnalysis['emergency_fund'] ?? [];
                if (! empty($ef['recommendation']) && strtolower($ef['category'] ?? '') !== 'excellent') {
                    $savingsRecs[] = [
                        'recommendation_text' => $ef['recommendation'],
                        'priority_score' => ($ef['category'] ?? '') === 'critical' ? 90 : 60,
                        'category' => 'emergency_fund',
                    ];
                }
                // ISA allowance recommendation
                $isa = $savingsAnalysis['isa_allowance'] ?? [];
                $remaining = $isa['remaining'] ?? 0;
                if ($remaining > 0) {
                    $savingsRecs[] = [
                        'recommendation_text' => 'You have £'.number_format($remaining).' of ISA allowance remaining this tax year. Consider maximising your tax-free savings.',
                        'priority_score' => 55,
                        'category' => 'isa_allowance',
                    ];
                }
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($savingsRecs, 'savings'));
            } catch (\Exception $e) {
                Log::warning("Failed to get savings recommendations for user {$userId}: ".$e->getMessage());
            }
        }

        // Retirement module — real recs come from the action-definition engine
        // via generateRecommendations(analyze data), NOT from an analyze() key.
        if (! $composedEnabled && $this->moduleGateOpen('retirement', $user)) {
            try {
                $retirementAnalysis = $this->retirementAgent->analyze($userId);
                $retirementData = $retirementAnalysis['data'] ?? $retirementAnalysis;
                $generated = $this->retirementAgent->generateRecommendations($retirementData);
                $retirementRecs = array_map(static function (array $r): array {
                    return [
                        'recommendation_text' => $r['title'] ?? $r['action'] ?? $r['description'] ?? '',
                        'priority_score' => isset($r['priority']) ? max(40, 90 - ((int) $r['priority'] * 5)) : 60,
                        'category' => $r['category'] ?? 'retirement',
                        'potential_benefit' => is_numeric($r['available_annual_headroom'] ?? null) ? $r['available_annual_headroom'] : null,
                    ];
                }, $generated['recommendations'] ?? []);
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($retirementRecs, 'retirement'));
            } catch (\Exception $e) {
                Log::warning("Failed to get retirement recommendations for user {$userId}: ".$e->getMessage());
            }
        }

        // Investment module
        if (! $composedEnabled && $this->moduleGateOpen('investment', $user)) {
            try {
                $investmentAnalysis = $this->investmentAgent->analyze($userId);
                $generated = $this->investmentAgent->generateRecommendations($investmentAnalysis['data'] ?? $investmentAnalysis);
                $investmentRecs = array_map(static function (array $r): array {
                    return [
                        'recommendation_text' => $r['title'] ?? $r['recommendation'] ?? $r['action'] ?? '',
                        'priority_score' => isset($r['priority']) ? max(40, 90 - ((int) $r['priority'] * 5)) : 55,
                        'category' => $r['category'] ?? 'investment',
                    ];
                }, $generated['recommendations'] ?? []);
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($investmentRecs, 'investment'));
            } catch (\Exception $e) {
                Log::warning("Failed to get investment recommendations for user {$userId}: ".$e->getMessage());
            }
        }

        // Estate module — extract from implementation_timeline
        if (! $composedEnabled && $this->moduleGateOpen('estate', $user)) {
            try {
                $estatePlan = $this->estatePlanService->generateComprehensiveEstatePlan($user);
                $estateRecs = [];
                // Extract actions from implementation_timeline
                $timeline = $estatePlan['implementation_timeline'] ?? [];
                foreach ($timeline as $item) {
                    if (is_array($item) && isset($item['action'])) {
                        $priority = ($item['priority'] ?? 2) === 1 ? 85 : 60;
                        $estateRecs[] = [
                            'recommendation_text' => $item['action'].(! empty($item['timeframe']) ? " ({$item['timeframe']})" : ''),
                            'priority_score' => $priority,
                            'category' => $item['category'] ?? 'estate_planning',
                            'estimated_cost' => $item['cost'] ?? null,
                            'potential_benefit' => is_numeric($item['iht_saving'] ?? null) ? $item['iht_saving'] : null,
                        ];
                    }
                }
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($estateRecs, 'estate'));
            } catch (\Exception $e) {
                Log::warning("Failed to get estate recommendations for user {$userId}: ".$e->getMessage());
            }
        }

        // Goals module
        if ($this->moduleGateOpen('goals', $user)) {
            try {
                $goalsAnalysis = $this->goalsAgent->analyze($userId);
                $generated = $this->goalsAgent->generateRecommendations($goalsAnalysis['data'] ?? $goalsAnalysis);
                $goalsRecs = array_map(static function (array $r): array {
                    return [
                        'recommendation_text' => $r['title'] ?? $r['action'] ?? $r['description'] ?? '',
                        'priority_score' => isset($r['priority']) ? max(40, 90 - ((int) $r['priority'] * 5)) : 50,
                        'category' => $r['category'] ?? 'goals',
                    ];
                }, $generated['recommendations'] ?? []);
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($goalsRecs, 'goals'));
            } catch (\Exception $e) {
                Log::warning("Failed to get goals recommendations for user {$userId}: ".$e->getMessage());
            }
        }

        // Tax module — the strategy catalogue, gated by the tax_optimisation prerequisites.
        // Locked strategies are deliberately NOT aggregated here: dashboards list
        // actionable recommendations; unlock prompts are a separate mobile surface.
        if ($this->moduleGateOpen('tax_optimisation', $user)) {
            try {
                $plan = $this->taxPlan->forUser($user);
                $taxRecs = array_map(static function (array $item): array {
                    return [
                        // Stable id derived from the strategy type — recommendation_tracking
                        // and the gamification dedup key both rely on identity across requests.
                        'recommendation_id' => 'tax_'.$item['type'],
                        'recommendation_text' => $item['title'].' — '.$item['description'],
                        'priority_score' => match ($item['priority']) {
                            'high' => 85.0, 'medium' => 60.0, default => 45.0,
                        },
                        'category' => $item['category'],
                        'potential_benefit' => $item['estimated_annual_tax_saved'],
                        'claim_tier' => $item['claim_tier'],
                        'sequence_position' => $item['sequence_position'],
                        'conflict_note' => $item['conflict_note'],
                    ];
                }, $plan['items']);
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($taxRecs, 'tax'));
            } catch (\Exception $e) {
                Log::warning("Failed to get tax recommendations for user {$userId}: ".$e->getMessage());
            }
        }

        // Personalise recommendations with user-specific context
        $allRecommendations = $this->personaliser->personaliseRecommendations($allRecommendations, $user);

        // Sort by priority score descending (highest priority first)
        usort($allRecommendations, function ($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return $allRecommendations;
    }

    /**
     * True when the named module's KYC prerequisites are satisfied for the user.
     * Modules map 1:1 to PrerequisiteGateService actions.
     */
    private function moduleGateOpen(string $module, User $user): bool
    {
        return $this->gate->enforce($module, $user)['can_proceed'] === true;
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
            $text = $rec['recommendation_text'] ?? $rec['recommendation'] ?? $rec['text'] ?? '';

            return [
                // Content-derived STABLE id: recommendation_tracking rows (mark-done)
                // and the gamification award dedup key (recommendation:{id}) both key
                // on this — it must be identical across requests for the same
                // logical recommendation, so never uniqid()/random.
                'recommendation_id' => $rec['recommendation_id'] ?? $rec['id'] ?? $module.'_'.substr(sha1($module.'|'.$text), 0, 16),
                'module' => $module,
                'recommendation_text' => $text,
                'priority_score' => $rec['priority_score'] ?? $rec['priority'] ?? 50.0,
                'timeline' => $rec['timeline'] ?? $this->determineTimeline($rec['priority_score'] ?? 50.0),
                'category' => $rec['category'] ?? $this->determineCategory($rec, $module),
                'impact' => $rec['impact'] ?? $this->determineImpact($rec['priority_score'] ?? 50.0),
                'estimated_cost' => $rec['estimated_cost'] ?? $rec['cost'] ?? null,
                'potential_benefit' => $rec['potential_benefit'] ?? $rec['benefit'] ?? null,
                'status' => $rec['status'] ?? 'pending',
                'claim_tier' => $rec['claim_tier'] ?? null,
                'sequence_position' => $rec['sequence_position'] ?? null,
                'conflict_note' => $rec['conflict_note'] ?? null,
            ];
        }, $validRecommendations);
    }

    /**
     * Map a module's composed plan into the aggregator recommendation shape,
     * mirroring the tax block. StrategyPlanComposer has already attached
     * claim_tier / sequence_position / conflict_note and resolved sequencing +
     * conflicts. Locked strategies are deliberately excluded (only $plan['items'])
     * — dashboards list actionable recommendations; unlock prompts are a separate
     * surface. Non-tax recommendations carry no estimated_annual_tax_saved, so
     * potential_benefit is null unless the source supplies one.
     *
     * @return list<array<string, mixed>>
     */
    private function composedModuleRecs(string $module, ModuleStrategySource $source, User $user): array
    {
        $plan = app(ComposedModulePlanService::class)->forSource($source, $user);

        $recs = array_map(static function (array $item) use ($module): array {
            $title = (string) ($item['title'] ?? '');
            $description = (string) ($item['description'] ?? '');

            return [
                'recommendation_id' => $module.'_'.($item['type'] ?? ''),
                'recommendation_text' => $description !== '' ? $title.' — '.$description : $title,
                'priority_score' => match ($item['priority'] ?? 'medium') {
                    'high' => 85.0,
                    'medium' => 60.0,
                    default => 45.0,
                },
                'category' => $item['category'] ?? null,
                'potential_benefit' => $item['estimated_annual_tax_saved'] ?? null,
                'claim_tier' => $item['claim_tier'] ?? null,
                'sequence_position' => $item['sequence_position'] ?? null,
                'conflict_note' => $item['conflict_note'] ?? null,
            ];
        }, $plan['items']);

        return $this->formatRecommendations($recs, $module);
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
            'goals' => 'goal_planning',
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
                'goals' => 0,
                'property' => 0,
                'tax' => 0,
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
            if (isset($rec['potential_benefit']) && is_numeric($rec['potential_benefit'])) {
                $summary['total_potential_benefit'] += $rec['potential_benefit'];
            }
            if (isset($rec['estimated_cost']) && is_numeric($rec['estimated_cost'])) {
                $summary['total_estimated_cost'] += $rec['estimated_cost'];
            }
        }

        return $summary;
    }
}
