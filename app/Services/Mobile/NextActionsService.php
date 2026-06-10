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

        $items = array_merge(
            $this->recommendationItems($userId),
            $this->unlockItems($user),
        );

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
                    : ucfirst(str_replace('_', ' ', (string) ($rec['category'] ?? 'Recommended'))),
                'value' => $benefit ?? (float) ($rec['priority_score'] ?? 50),
                'done' => in_array($id, $completedIds, true),
                'action' => ['kind' => 'rec_chat', 'payload' => (string) ($rec['recommendation_text'] ?? '')],
            ];
        }, $all);
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
