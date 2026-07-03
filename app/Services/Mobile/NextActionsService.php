<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\PrerequisiteGateService;

/**
 * Builds the single ranked next-actions list (max 4) shown on the /m dashboard
 * wheel box AND the recommendations list below — they are the same list. Items
 * are either real recommendations (from the KYC-gated aggregator) or KYC
 * "unlock" prompts for high-value gated modules.
 */
class NextActionsService
{
    private const MAX_ITEMS = 4;

    /** Modules that can produce an unlock prompt, in surfacing priority order. */
    private const UNLOCK_MODULES = ['retirement', 'protection', 'savings', 'investment', 'estate', 'goals'];

    /** Max strategy-level unlock cards to surface — keeps the 4-slot list from being crowded. */
    private const MAX_STRATEGY_UNLOCKS = 2;

    /**
     * WP-6 — campaign-to-module affinity map. Campaign arrivals see their
     * campaign's primary module surfaced ahead of generic cross-module items.
     * Unknown or absent campaign tokens receive no affinity boost.
     */
    private const CAMPAIGN_AFFINITY = [
        'savetax' => 'tax',
        'pensioncheck' => 'retirement',
    ];

    public function __construct(
        private readonly RecommendationsAggregatorService $recommendations,
        private readonly PrerequisiteGateService $gate,
        private readonly ComposedTaxPlanService $taxPlan,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function build(int $userId): array
    {
        $user = User::findOrFail($userId);

        return array_slice($this->applyCampaignAffinity($user, $this->rankAll($user, $userId)), 0, self::MAX_ITEMS);
    }

    /**
     * WP-2 (one actions model) — the FULL ranked open-actions list, uncapped.
     * Same items, same ranking, same stable ids as build(); the 4-slot cap is
     * a dashboard presentation concern, not a property of the list. Feeds the
     * "all my actions" surfaces (desktop /actions, /m Done/all views).
     *
     * @return array<int,array<string,mixed>>
     */
    public function buildAll(int $userId): array
    {
        $user = User::findOrFail($userId);

        return $this->applyCampaignAffinity($user, $this->rankAll($user, $userId));
    }

    /**
     * The merged, value-ranked open list shared by build()/buildAll().
     *
     * @return array<int,array<string,mixed>>
     */
    private function rankAll(User $user, int $userId): array
    {
        $items = array_merge(
            $this->recommendationItems($userId),
            $this->unlockItems($user),
            $this->strategyUnlockItems($user),
        );

        usort($items, static function (array $a, array $b): int {
            return [$b['value'], $a['module']] <=> [$a['value'], $b['module']];
        });

        return $items;
    }

    /**
     * Per-area focus cards for the /m carousel: a "Top actions" card (the unified
     * <=4 across all areas) followed by one card per module — real recommendations
     * when the module's KYC gate is open, or a locked "unlock" card when it is
     * gated. Selecting a card drives the actions list shown below it. Computed
     * from a single recommendation aggregation.
     *
     * @return array<int,array<string,mixed>>
     */
    public function focusAreas(int $userId): array
    {
        $user = User::findOrFail($userId);

        $recItems = $this->recommendationItems($userId);
        $unlocks = array_merge($this->unlockItems($user), $this->strategyUnlockItems($user));

        // Top card = the unified <=4 (recs + unlocks), same ranking as build()
        // including the WP-6 campaign affinity (tax first for SaveTax users).
        // Affinity runs BEFORE the 4-slot cut so a lower-value tax item can
        // still be lifted into the card.
        $merged = array_merge($recItems, $unlocks);
        usort($merged, static function (array $a, array $b): int {
            return [$b['value'], $a['module']] <=> [$a['value'], $b['module']];
        });
        $top = array_slice($this->applyCampaignAffinity($user, $merged), 0, self::MAX_ITEMS);

        // Group real recommendations by module for the per-area cards.
        $byModule = [];
        foreach ($recItems as $item) {
            $byModule[$item['module']][] = $item;
        }
        foreach ($byModule as &$list) {
            usort($list, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        }
        unset($list);

        $unlockByModule = [];
        foreach ($unlocks as $unlock) {
            $unlockByModule[$unlock['module']] = $unlock;
        }

        $areas = [[
            'key' => 'top',
            'label' => 'Top actions',
            'locked' => false,
            'stat' => count($top).' action'.(count($top) === 1 ? '' : 's'),
            'actions' => $top,
        ]];

        foreach (self::UNLOCK_MODULES as $module) {
            $label = ucfirst($this->moduleLabel($module));

            if (isset($unlockByModule[$module])) {
                $unlock = $unlockByModule[$module];
                $areas[] = [
                    'key' => $module,
                    'label' => $label,
                    'locked' => true,
                    'stat' => (string) $unlock['meta'],
                    'actions' => [$unlock],
                ];

                continue;
            }

            // KYC gate open → this module's real recommendations.
            $items = array_slice($byModule[$module] ?? [], 0, self::MAX_ITEMS);

            if ($items === []) {
                // Gate open but no recommendations means we don't yet have enough
                // data to advise on this module. Show the KYC prompt for what's
                // needed — NEVER an "On track"/empty placeholder. A module says
                // either real recommendations or the data still needed to make them.
                $needed = $this->dataNeededItem($module);
                $areas[] = [
                    'key' => $module,
                    'label' => $label,
                    'locked' => true,
                    'stat' => (string) $needed['meta'],
                    'actions' => [$needed],
                ];

                continue;
            }

            $areas[] = [
                'key' => $module,
                'label' => $label,
                'locked' => false,
                'stat' => (string) ($items[0]['meta'] ?? (count($items).' actions')),
                'actions' => $items,
            ];
        }

        return $areas;
    }

    /**
     * KYC prompt for a module whose gate is open but which can't yet produce
     * recommendations (not enough data). Same shape as a gate-closed unlock.
     *
     * @return array<string,mixed>
     */
    private function dataNeededItem(string $module): array
    {
        $label = $this->moduleLabel($module);

        return [
            'id' => 'unlock:'.$module,
            'type' => 'unlock',
            'module' => $module,
            'title' => 'Complete your '.$label.' details',
            'meta' => 'A few more details so we can give you '.$label.' recommendations',
            'value' => 0.0,
            'done' => false,
            'action' => ['kind' => 'fyn_capture', 'payload' => $module],
        ];
    }

    /**
     * Rank by value descending (module name tie-break) and cap at MAX_ITEMS.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    private function rank(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            return [$b['value'], $a['module']] <=> [$a['value'], $b['module']];
        });

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * WP-6 — campaign affinity. Campaign arrivals see their campaign's primary
     * module sorted ahead of generic cross-module items; within each tier the
     * normal value ranking holds. The module is keyed off CAMPAIGN_AFFINITY via
     * the user's onboarding_fyn_selection (set at campaign start) with a
     * fallback to funnel_answers['campaign'] (raw funnel data). Unknown or
     * absent tokens receive no affinity boost.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    private function applyCampaignAffinity(User $user, array $items): array
    {
        $raw = $user->onboarding_fyn_selection
            ?? ($user->funnel_answers['campaign'] ?? null);

        $campaign = is_string($raw) ? $raw : null;
        $affinityModule = self::CAMPAIGN_AFFINITY[$campaign] ?? null;

        if ($affinityModule === null) {
            return $items;
        }

        usort($items, static function (array $a, array $b) use ($affinityModule): int {
            $aMatch = ($a['module'] ?? '') === $affinityModule ? 1 : 0;
            $bMatch = ($b['module'] ?? '') === $affinityModule ? 1 : 0;

            return [$bMatch, $b['value'], $a['module']] <=> [$aMatch, $a['value'], $b['module']];
        });

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function recommendationItems(int $userId): array
    {
        $all = $this->recommendations->aggregateRecommendations($userId);

        $completedIds = RecommendationTracking::where('user_id', $userId)
            ->where('status', 'completed')
            ->pluck('recommendation_id')
            ->all();

        // Drop blank recs (a blank renders as an empty row / "How do I ''?")
        // AND completed recs: a completed action is banked toward the wheel
        // count and replaced by the next-best, so it leaves the actionable
        // list rather than sitting there ticked (CSJ 4.4 — replace done with a
        // new one; the running tally is counted in MobileLevelService).
        $all = array_filter($all, static function (array $rec) use ($completedIds): bool {
            if (trim((string) ($rec['recommendation_text'] ?? '')) === '') {
                return false;
            }

            return ! in_array((string) ($rec['recommendation_id'] ?? ''), $completedIds, true);
        });

        return array_map(function (array $rec): array {
            $benefit = is_numeric($rec['potential_benefit'] ?? null) ? (float) $rec['potential_benefit'] : null;
            $id = (string) ($rec['recommendation_id'] ?? uniqid('rec_'));

            return [
                'id' => $id,
                'type' => 'recommendation',
                'module' => (string) ($rec['module'] ?? 'general'),
                'title' => (string) ($rec['recommendation_text'] ?? ''),
                'meta' => $benefit !== null
                    ? 'You could save £'.number_format($benefit)
                    : $this->categoryLabel((string) ($rec['category'] ?? 'Recommended')),
                'value' => $benefit ?? (float) ($rec['priority_score'] ?? 50),
                // Open only — completed recs are excluded above and replaced by
                // the next-best, so every shown recommendation is actionable.
                'done' => false,
                // Tapping a recommendation deep-links to the module screen where
                // the user actions it (NOT a templated Fyn message).
                'action' => ['kind' => 'navigate', 'payload' => $this->moduleRoute((string) ($rec['module'] ?? 'general'))],
            ];
        }, $all);
    }

    /**
     * The in-app /m route for a module — where a recommendation is actioned.
     */
    private function moduleRoute(string $module): string
    {
        return match ($module) {
            'protection' => '/protection',
            'savings' => '/savings',
            'investment' => '/investment',
            'retirement' => '/retirement',
            'estate' => '/estate',
            'goals' => '/goals',
            'tax' => '/tax-strategy',
            default => '/net-worth',
        };
    }

    /**
     * Human-readable category label, preserving "ISA" casing (Rule #9).
     */
    private function categoryLabel(string $category): string
    {
        $label = ucwords(str_replace('_', ' ', $category));

        return preg_replace('/\bIsa\b/', 'ISA', $label) ?? $label;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function unlockItems(User $user): array
    {
        $weight = (float) config('gamification.unlock_action_weight', 65);
        $items = [];

        foreach (self::UNLOCK_MODULES as $module) {
            $gate = $this->gate->enforce($module, $user);
            if ($gate['can_proceed'] === true) {
                continue;
            }

            $action = $gate['required_actions'][0] ?? ['label' => 'Add your details', 'route' => '/dashboard'];

            $items[] = [
                'id' => 'unlock:'.$module,
                'type' => 'unlock',
                'module' => $module,
                'title' => 'Unlock '.$this->moduleLabel($module).' advice',
                'meta' => $action['label'] ?? 'A few quick questions',
                'value' => $weight,
                'done' => false,
                'action' => ['kind' => 'fyn_capture', 'payload' => $module],
            ];
        }

        return $items;
    }

    /**
     * Strategy-level unlock cards: tax gate open but individual strategies are
     * locked by a single missing data point each. Teases the unlock to the user
     * and routes to tax strategy capture. Uses type 'unlock' so the frontend
     * carousel styles and tap-handler (openFynForCapture) work without changes;
     * the id prefix 'strategy_unlock:' distinguishes them from module unlocks.
     * At most MAX_STRATEGY_UNLOCKS items so they never crowd the 4-slot list.
     *
     * These carry a slightly higher weight than module-level unlocks (+5) because
     * they are more specific: we know the exact missing data point, which makes
     * them more actionable than a generic "unlock module" prompt.
     *
     * @return array<int,array<string,mixed>>
     */
    private function strategyUnlockItems(User $user): array
    {
        if ($this->gate->enforce('tax_optimisation', $user)['can_proceed'] !== true) {
            return [];
        }

        $plan = $this->taxPlan->forUser($user);
        $weight = (float) config('gamification.unlock_action_weight', 65) + 5.0;
        $items = [];

        foreach (array_slice($plan['locked'], 0, self::MAX_STRATEGY_UNLOCKS) as $locked) {
            $noun = $this->unlockNounFor((string) ($locked['missing'][0] ?? ''));

            $items[] = [
                'id' => 'strategy_unlock:'.$locked['strategy_type'],
                'type' => 'unlock',
                'module' => 'tax',
                // Per-item label (CSJ 4.2): name the specific missing detail
                // ("Unlock pension info" / "Enter your pension details") rather
                // than a generic "Unlock a tax strategy" — the user has already
                // seen a strategy for what they have; this is about adding more.
                'title' => 'Unlock '.$noun.' info',
                'meta' => 'Enter your '.$noun.' details',
                'value' => $weight,
                'done' => false,
                'action' => ['kind' => 'fyn_capture', 'payload' => 'tax'],
            ];
        }

        return $items;
    }

    /**
     * Short noun for an unlock card title, derived from the missing data point
     * (CSJ 4.2). Keeps the card item-specific ("pension", "ISA") rather than the
     * verbose data-point label; falls back to the household-context label.
     */
    private function unlockNounFor(string $missingKey): string
    {
        return match ($missingKey) {
            'pension_contributions', 'workplace_pension', 'pension_input_history' => 'pension',
            'isa_subscriptions_ytd' => 'ISA',
            'gia_holdings' => 'investment',
            'dividend_income' => 'dividend',
            'savings_balances' => 'savings',
            'annual_income' => 'income',
            'spouse_income' => "spouse's income",
            default => HouseholdFinancialContext::labelFor($missingKey),
        };
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'protection' => 'protection',
            'savings' => 'savings',
            'investment' => 'investment',
            'retirement' => 'retirement',
            'estate' => 'estate planning',
            'goals' => 'goals',
            default => $module,
        };
    }
}
