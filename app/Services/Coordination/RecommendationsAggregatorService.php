<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\RecommendationTracking;
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

/**
 * Collects each module's recommendations for the web API, the dashboard focus
 * cards and /m next actions, then hands the whole set to PriorityRanker once.
 * The ranking rule lives there (fyn-wiring Batch B, F2/F12): this class only
 * gathers and shapes; it holds no bands of its own.
 */
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
        private readonly PriorityRanker $ranker = new PriorityRanker,
    ) {}

    /**
     * Aggregate recommendations from all modules, ranked highest first.
     *
     * @return list<array<string, mixed>>
     */
    public function aggregateRecommendations(int $userId): array
    {
        $user = User::findOrFail($userId);

        /** @var array<string, list<array<string, mixed>>> $byModule raw recs carrying the engine's own priority */
        $byModule = [];
        $collect = function (string $module, callable $source) use (&$byModule, $userId): void {
            try {
                $byModule[$module] = array_merge($byModule[$module] ?? [], $source());
            } catch (\Exception $e) {
                Log::warning("Failed to get {$module} recommendations for user {$userId}: ".$e->getMessage());
            }
        };

        // When enabled, the five non-tax modules are sourced from the generalised
        // ComposedModulePlanService (catalogue-annotated, sequencing-aware) instead
        // of their raw per-agent blocks below; the raw blocks remain as the rollback
        // path (flag off). Locked strategies are excluded — dashboards list actionable
        // recommendations; unlock prompts surface via Fyn pointers / the holistic plan.
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
                if ($this->moduleGateOpen($module, $user)) {
                    $collect($module, fn () => $this->composedModuleRecs($module, app($sourceClass), $user));
                }
            }
        }

        if (! $composedEnabled && $this->moduleGateOpen('protection', $user)) {
            $collect('protection', function () use ($userId): array {
                $analysis = $this->protectionEngine->analyze($userId);
                $rawRecs = $analysis['data']['recommendations'] ?? [];
                $recs = array_map(static fn (array $r): array => [
                    'recommendation_id' => $r['recommendation_id'] ?? $r['id'] ?? null,
                    'recommendation_text' => $r['title'] ?? $r['action'] ?? $r['description'] ?? $r['recommendation_text'] ?? '',
                    'priority' => $r['priority'] ?? null,
                    'impact' => $r['impact'] ?? null,
                    'category' => $r['category'] ?? null,
                    'estimated_cost' => $r['estimated_cost'] ?? null,
                    'potential_benefit' => $r['potential_benefit'] ?? null,
                ], array_filter(is_array($rawRecs) ? $rawRecs : [], 'is_array'));
                foreach (($analysis['data']['gaps'] ?? []) as $gap) {
                    if (is_array($gap) && ! empty($gap['recommendation'])) {
                        $recs[] = [
                            'recommendation_text' => $gap['recommendation'],
                            'priority' => 'high',
                            'category' => $gap['type'] ?? 'coverage_gap',
                        ];
                    }
                }

                return $recs;
            });
        }

        if (! $composedEnabled && $this->moduleGateOpen('savings', $user)) {
            $collect('savings', function () use ($userId): array {
                $analysis = $this->savingsCalculator->analyze($userId);
                $recs = [];
                $ef = $analysis['emergency_fund'] ?? [];
                $runway = is_numeric($ef['runway_months'] ?? null) ? (float) $ef['runway_months'] : null;
                $targetMonths = (int) ($ef['target_months'] ?? 6);
                if (! empty($ef['recommendation']) && $runway !== null && $runway < $targetMonths) {
                    $recs[] = [
                        'recommendation_text' => $ef['recommendation'],
                        'priority' => $runway < 1 ? 'critical' : 'medium',
                        'category' => 'emergency_fund',
                    ];
                }
                $remaining = $analysis['isa_allowance']['remaining'] ?? 0;
                if ($remaining > 0) {
                    $recs[] = [
                        'recommendation_text' => 'You have £'.number_format($remaining).' of ISA allowance remaining this tax year. Consider maximising your tax-free savings.',
                        'priority' => 'medium',
                        'category' => 'isa_allowance',
                    ];
                }

                return $recs;
            });
        }

        // Retirement, investment and goals: the action-definition engines via
        // generateRecommendations(analyze data); each rec carries the seeded label.
        if (! $composedEnabled && $this->moduleGateOpen('retirement', $user)) {
            $collect('retirement', function () use ($userId): array {
                $analysis = $this->retirementAgent->analyze($userId);
                $generated = $this->retirementAgent->generateRecommendations($analysis['data'] ?? $analysis);

                return array_map(static fn (array $r): array => [
                    'recommendation_id' => $r['recommendation_id'] ?? $r['id'] ?? null,
                    'recommendation_text' => $r['title'] ?? $r['action'] ?? $r['description'] ?? '',
                    'priority' => $r['priority'] ?? null,
                    'impact' => $r['impact'] ?? null,
                    'category' => $r['category'] ?? 'retirement',
                    'potential_benefit' => is_numeric($r['available_annual_headroom'] ?? null) ? $r['available_annual_headroom'] : null,
                ], $generated['recommendations'] ?? []);
            });
        }

        if (! $composedEnabled && $this->moduleGateOpen('investment', $user)) {
            $collect('investment', function () use ($userId): array {
                $analysis = $this->investmentAgent->analyze($userId);
                $generated = $this->investmentAgent->generateRecommendations($analysis['data'] ?? $analysis);

                return array_map(static fn (array $r): array => [
                    'recommendation_id' => $r['recommendation_id'] ?? $r['id'] ?? null,
                    'recommendation_text' => $r['title'] ?? $r['recommendation'] ?? $r['action'] ?? '',
                    'priority' => $r['priority'] ?? null,
                    'impact' => $r['impact'] ?? null,
                    'category' => $r['category'] ?? 'investment',
                ], $generated['recommendations'] ?? []);
            });
        }

        if (! $composedEnabled && $this->moduleGateOpen('estate', $user)) {
            $collect('estate', function () use ($user): array {
                $plan = $this->estatePlanService->generateComprehensiveEstatePlan($user);
                $recs = [];
                foreach ($plan['implementation_timeline'] ?? [] as $item) {
                    if (is_array($item) && isset($item['action'])) {
                        $recs[] = [
                            'recommendation_text' => $item['action'].(! empty($item['timeframe']) ? " ({$item['timeframe']})" : ''),
                            'priority' => $item['priority'] ?? 2,
                            'category' => $item['category'] ?? 'estate_planning',
                            'estimated_cost' => $item['cost'] ?? null,
                            'potential_benefit' => is_numeric($item['iht_saving'] ?? null) ? $item['iht_saving'] : null,
                        ];
                    }
                }

                return $recs;
            });
        }

        if ($this->moduleGateOpen('goals', $user)) {
            $collect('goals', function () use ($userId): array {
                $analysis = $this->goalsAgent->analyze($userId);
                $generated = $this->goalsAgent->generateRecommendations($analysis['data'] ?? $analysis);

                return array_map(static fn (array $r): array => [
                    'recommendation_id' => $r['recommendation_id'] ?? $r['id'] ?? null,
                    'recommendation_text' => $r['title'] ?? $r['action'] ?? $r['description'] ?? '',
                    'priority' => $r['priority'] ?? null,
                    'impact' => $r['impact'] ?? null,
                    'category' => $r['category'] ?? 'goals',
                ], $generated['recommendations'] ?? []);
            });
        }

        // Tax: the strategy catalogue, gated by the tax_optimisation prerequisites.
        if ($this->moduleGateOpen('tax_optimisation', $user)) {
            $collect('tax', function () use ($user): array {
                return array_map(static fn (array $item): array => [
                    // Stable id derived from the strategy type — recommendation_tracking
                    // and the gamification dedup key both rely on identity across requests.
                    'recommendation_id' => 'tax_'.$item['type'],
                    'recommendation_text' => $item['title'].' — '.$item['description'],
                    'priority' => $item['priority'],
                    'category' => $item['category'],
                    'potential_benefit' => $item['estimated_annual_tax_saved'],
                    'claim_tier' => $item['claim_tier'],
                    'sequence_position' => $item['sequence_position'],
                    'conflict_note' => $item['conflict_note'],
                ], $this->taxPlan->forUser($user)['items']);
            });
        }

        $ranked = $this->ranker->rankRecommendations($byModule);
        // recommendation_tracking is the one status ledger (mark-done, dismiss);
        // merging it here means every consumer and the ?status= filter read it (F18).
        $statuses = RecommendationTracking::where('user_id', $userId)->pluck('status', 'recommendation_id')->all();
        $shaped = array_map(fn (array $rec): array => $this->shape($rec, $statuses), $ranked);

        return $this->personaliser->personaliseRecommendations($shaped, $user);
    }

    /**
     * True when the named module's KYC prerequisites are satisfied for the user.
     * Modules map 1:1 to PrerequisiteGateService actions.
     */
    private function moduleGateOpen(string $module, User $user): bool
    {
        $userId = $user->id;

        return $this->gate->enforce($module, $user)['can_proceed'] === true;
    }

    /**
     * The API shape, from a ranked recommendation.
     *
     * @param  array<string, mixed>  $rec
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, string>  $statuses  recommendation_id => tracking status
     */
    private function shape(array $rec, array $statuses): array
    {
        $module = (string) $rec['module'];
        $text = (string) ($rec['recommendation_text'] ?? $rec['recommendation'] ?? $rec['text'] ?? '');

        return [
            // Content-derived STABLE id: recommendation_tracking rows (mark-done)
            // and the gamification award dedup key (recommendation:{id}) both key
            // on this — it must be identical across requests for the same
            // logical recommendation, so never uniqid()/random.
            'recommendation_id' => $rec['recommendation_id'] ?? $rec['id'] ?? $module.'_'.substr(sha1($module.'|'.$text), 0, 16),
            'rule_key' => $rec['rule_key'] ?? null,
            'module' => $module,
            'recommendation_text' => $text,
            'priority_score' => $rec['priority_score'],
            'timeline' => $rec['timeline'],
            'category' => $rec['category'] ?? self::DEFAULT_CATEGORY[$module] ?? 'general',
            'impact' => $rec['impact_label'],
            'estimated_cost' => $rec['estimated_cost'] ?? $rec['cost'] ?? null,
            'potential_benefit' => $rec['potential_benefit'] ?? $rec['benefit'] ?? null,
            'status' => $statuses[$rec['recommendation_id'] ?? $rec['id'] ?? ''] ?? $rec['status'] ?? 'pending',
            'claim_tier' => $rec['claim_tier'] ?? null,
            'sequence_position' => $rec['sequence_position'] ?? null,
            'conflict_note' => $rec['conflict_note'] ?? null,
            // The record the recommendation is about (an account-scoped
            // savings/pension rule, a goal rule) so the dashboard row can
            // deep-link to THAT record's page (RecommendationRouting).
            'account_id' => $rec['account_id'] ?? null,
            'goal_id' => $rec['goal_id'] ?? null,
        ];
    }

    private const DEFAULT_CATEGORY = [
        'protection' => 'risk_mitigation',
        'savings' => 'liquidity_management',
        'investment' => 'growth_optimization',
        'retirement' => 'retirement_planning',
        'estate' => 'tax_optimization',
        'goals' => 'goal_planning',
    ];

    /**
     * Map a module's composed plan into raw aggregator recs, mirroring the tax
     * block. StrategyPlanComposer has already attached claim_tier /
     * sequence_position / conflict_note and resolved sequencing + conflicts.
     * Locked strategies are deliberately excluded (only $plan['items']).
     * The adapters carry the seeded label as seeded_priority because the
     * StrategyPriority enum has no critical case.
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
                'recommendation_id' => self::composeId($module, $item),
                // The rule behind the id, without the record scope — what
                // RecommendationRouting keys on.
                'rule_key' => $module.'_'.($item['type'] ?? ''),
                'recommendation_text' => $description !== '' ? $title.' — '.$description : $title,
                'priority' => $item['seeded_priority'] ?? $item['priority'] ?? 'medium',
                'category' => $item['category'] ?? null,
                'potential_benefit' => $item['estimated_annual_tax_saved'] ?? $item['estimated_iht_saving'] ?? null,
                'estimated_impact' => $item['estimated_impact'] ?? null,
                'claim_tier' => $item['claim_tier'] ?? null,
                'sequence_position' => $item['sequence_position'] ?? null,
                'conflict_note' => $item['conflict_note'] ?? null,
                'account_id' => $item['account_id'] ?? null,
                'goal_id' => $item['goal_id'] ?? null,
            ];
        }, $plan['items']);

        return self::disambiguate($recs);
    }

    /** The record a rule can be about, in precedence order, and the id segment each contributes. */
    private const SCOPE_KEYS = [
        'account_id' => 'a',
        'goal_id' => 'g',
        'family_member_id' => 'm',
        'life_event_id' => 'e',
        'policy_id' => 'p',
    ];

    /**
     * The stable id: {module}_{type}, plus the record when the rule names one.
     * A per-account / per-goal / per-child / per-policy rule is one
     * recommendation PER RECORD; sharing the id across instances let one
     * mark-done complete them all (CSJ 2026-09-10). Rules that name no record
     * keep the bare id, so nothing already tracked changes.
     *
     * @param  array<string, mixed>  $item
     */
    public static function composeId(string $module, array $item): string
    {
        $id = $module.'_'.($item['type'] ?? '');

        foreach (self::SCOPE_KEYS as $key => $segment) {
            if (is_numeric($item[$key] ?? null)) {
                return $id.'_'.$segment.(int) $item[$key];
            }
        }

        return $id;
    }

    /**
     * Ids that still collide within one plan (two curated categories mapped to
     * one type, a repeating rule that carries no record id) get a suffix from
     * the headline, deterministic across requests and distinct per instance.
     * ponytail: headline hash; a reworded headline restarts that row's done
     * status. Give the rule a record id in its adapter instead of extending this.
     *
     * @param  list<array<string, mixed>>  $recs
     * @return list<array<string, mixed>>
     */
    public static function disambiguate(array $recs): array
    {
        $counts = array_count_values(array_column($recs, 'recommendation_id'));

        foreach ($recs as &$rec) {
            if (($counts[$rec['recommendation_id']] ?? 0) > 1) {
                $headline = explode(' — ', (string) ($rec['recommendation_text'] ?? ''), 2)[0];
                $rec['recommendation_id'] .= '_'.substr(sha1($headline), 0, 8);
            }
        }

        return $recs;
    }

    /**
     * Get recommendations filtered by module.
     */
    public function getRecommendationsByModule(int $userId, string $module): array
    {
        return array_filter($this->aggregateRecommendations($userId), fn ($rec) => $rec['module'] === $module);
    }

    /**
     * Get recommendations filtered by priority (the UI impact label).
     */
    public function getRecommendationsByPriority(int $userId, string $priority): array
    {
        return array_filter($this->aggregateRecommendations($userId), fn ($rec) => $rec['impact'] === $priority);
    }

    /**
     * Get recommendations filtered by timeline.
     */
    public function getRecommendationsByTimeline(int $userId, string $timeline): array
    {
        return array_filter($this->aggregateRecommendations($userId), fn ($rec) => $rec['timeline'] === $timeline);
    }

    /**
     * Get top N recommendations by priority.
     */
    public function getTopRecommendations(int $userId, int $limit = 5): array
    {
        return array_slice($this->aggregateRecommendations($userId), 0, $limit);
    }

    /**
     * Get summary statistics.
     */
    public function getSummary(int $userId): array
    {
        $allRecommendations = $this->aggregateRecommendations($userId);

        $summary = [
            'total_count' => count($allRecommendations),
            'by_priority' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'by_module' => array_fill_keys(['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'property', 'tax'], 0),
            'by_timeline' => array_fill_keys(['immediate', 'short_term', 'medium_term', 'long_term'], 0),
            'total_potential_benefit' => 0,
            'total_estimated_cost' => 0,
        ];

        foreach ($allRecommendations as $rec) {
            $impact = $rec['impact'] ?? 'medium';
            $summary['by_priority'][$impact] = ($summary['by_priority'][$impact] ?? 0) + 1;

            $module = $rec['module'] ?? 'general';
            if (isset($summary['by_module'][$module])) {
                $summary['by_module'][$module]++;
            }

            $timeline = $rec['timeline'] ?? 'medium_term';
            $summary['by_timeline'][$timeline] = ($summary['by_timeline'][$timeline] ?? 0) + 1;

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
