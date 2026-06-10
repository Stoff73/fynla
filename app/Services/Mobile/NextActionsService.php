<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\User;
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

    public function __construct(
        private readonly RecommendationsAggregatorService $recommendations,
        private readonly PrerequisiteGateService $gate,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function build(int $userId): array
    {
        $user = User::findOrFail($userId);

        return $this->rank(array_merge(
            $this->recommendationItems($userId),
            $this->unlockItems($user),
        ));
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
        $unlocks = $this->unlockItems($user);

        // Top card = the unified <=4 (recs + unlocks), same ranking as build().
        $top = $this->rank(array_merge($recItems, $unlocks));

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
     * @return array<int,array<string,mixed>>
     */
    private function recommendationItems(int $userId): array
    {
        $all = $this->recommendations->aggregateRecommendations($userId);

        $completedIds = RecommendationTracking::where('user_id', $userId)
            ->where('status', 'completed')
            ->pluck('recommendation_id')
            ->all();

        // Drop recommendations with no human-readable text — a blank rec renders
        // as an empty row / "How do I ''?" and is worse than showing nothing.
        $all = array_filter($all, static fn (array $rec): bool => trim((string) ($rec['recommendation_text'] ?? '')) !== '');

        return array_map(function (array $rec) use ($completedIds): array {
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
                'done' => in_array($id, $completedIds, true),
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
