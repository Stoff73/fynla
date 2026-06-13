<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\RecommendationTracking;
use App\Models\User;

class LevelService
{
    /** @return array<int,array{name:string,min_points:int}> */
    private function levels(): array
    {
        return config('gamification.levels');
    }

    public function levelForPoints(int $points): int
    {
        $resolved = 1;
        foreach ($this->levels() as $level => $def) {
            if ($points >= $def['min_points']) {
                $resolved = $level;
            }
        }

        return $resolved;
    }

    public function levelName(int $level): string
    {
        return $this->levels()[$level]['name'] ?? 'Starter';
    }

    /**
     * @return array{level:int, level_name:string, level_label:string,
     *               progress_percent:int, next_level_name:?string}
     */
    public function progress(int $points): array
    {
        $levels = $this->levels();
        $level = $this->levelForPoints($points);
        $name = $this->levelName($level);
        $maxLevel = array_key_last($levels);

        if ($level >= $maxLevel) {
            return [
                'level' => $level,
                'level_name' => $name,
                'level_label' => "Level {$level} · {$name}",
                'progress_percent' => 100,
                'next_level_name' => null,
            ];
        }

        $bandStart = $levels[$level]['min_points'];
        $bandEnd = $levels[$level + 1]['min_points'];
        $band = max(1, $bandEnd - $bandStart);
        $pct = (int) round((($points - $bandStart) / $band) * 100);

        return [
            'level' => $level,
            'level_name' => $name,
            'level_label' => "Level {$level} · {$name}",
            'progress_percent' => max(0, min(100, $pct)),
            'next_level_name' => $levels[$level + 1]['name'],
        ];
    }

    /**
     * Action-oriented "what's next" — plain-text imperatives derived from the
     * user's highest-value unfilled actions. NEVER mentions points (Rule #12 /
     * decision #7). Returns up to 2 suggestions.
     *
     * @return array<int,string>
     */
    public function nextActions(User $user): array
    {
        $suggestions = [];

        $checks = [
            'savingsAccounts' => 'Add a savings account',
            'investmentAccounts' => 'Add an investment account',
            'dcPensions' => 'Add a pension',
            'properties' => 'Add a property',
            'protectionPolicies' => 'Add a protection policy',
            'goals' => 'Set a financial goal',
        ];

        foreach ($checks as $relation => $label) {
            if (method_exists($user, $relation) && $user->{$relation}()->count() === 0) {
                $suggestions[] = $label;
            }
            if (count($suggestions) >= 2) {
                return $suggestions;
            }
        }

        // Fall back to completing open recommendations.
        $openRecs = RecommendationTracking::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();
        if ($openRecs > 0 && count($suggestions) < 2) {
            $suggestions[] = $openRecs === 1
                ? 'Complete your open recommendation'
                : "Complete {$openRecs} open recommendations";
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Keep your information up to date';
        }

        return array_slice($suggestions, 0, 2);
    }
}
