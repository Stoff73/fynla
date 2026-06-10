<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\PrerequisiteGateService;
use Illuminate\Support\Facades\Cache;

/**
 * Composite "planning progress" score (0..100) and a peer percentile.
 *
 * The percentile cohort is the viewer's own preview class (preview personas
 * ranked among preview personas; real users among real users) so preview
 * testing is meaningful AND prod percentiles never include seeded personas.
 * (CSJ-approved Rule #12 gamification metric — NOT a financial-quality score.)
 */
class PlanningProgressService
{
    private const DISTRIBUTION_TTL = 3600; // 1 hour — slow-moving

    private const W_MODULES = 40;

    private const W_RECS = 25;

    private const W_MILESTONES = 20;

    private const W_UNIVERSAL = 15;

    private const MODULES = ['protection', 'savings', 'retirement', 'investment', 'estate', 'goals'];

    public function __construct(private readonly PrerequisiteGateService $gate) {}

    public function scoreFor(User $user): int
    {
        $openModules = 0;
        foreach (self::MODULES as $module) {
            if ($this->gate->enforce($module, $user)['can_proceed'] === true) {
                $openModules++;
            }
        }
        $moduleScore = ($openModules / count(self::MODULES)) * self::W_MODULES;

        $completed = RecommendationTracking::where('user_id', $user->id)
            ->where('status', 'completed')->count();
        $recScore = (min($completed, 10) / 10) * self::W_RECS;

        $milestones = UserMilestone::where('user_id', $user->id)->count();
        $milestoneScore = (min($milestones, 8) / 8) * self::W_MILESTONES;

        $universal = 0;
        $universal += $user->date_of_birth ? 1 : 0;
        $universal += $user->marital_status ? 1 : 0;
        $universal += $user->employment_status ? 1 : 0;
        $universal += $this->totalIncome($user) > 0 ? 1 : 0;
        $universal += ((float) ($user->monthly_expenditure ?? 0)) > 0 ? 1 : 0;
        $universalScore = ($universal / 5) * self::W_UNIVERSAL;

        return (int) round($moduleScore + $recScore + $milestoneScore + $universalScore);
    }

    public function percentileFor(User $user): int
    {
        $score = $this->scoreFor($user);
        $distribution = $this->distribution((bool) $user->is_preview_user);

        $total = count($distribution);
        if ($total <= 1) {
            return 50;
        }

        $below = count(array_filter($distribution, static fn (int $s) => $s < $score));
        $pct = (int) round(($below / $total) * 100);

        return max(1, min(99, $pct));
    }

    /**
     * @return array<int,int>
     */
    private function distribution(bool $preview): array
    {
        $key = 'planning_progress_dist:'.($preview ? 'preview' : 'real');

        return Cache::remember($key, self::DISTRIBUTION_TTL, function () use ($preview) {
            return User::query()
                ->where('is_preview_user', $preview)
                ->get()
                ->map(fn (User $u) => $this->scoreFor($u))
                ->all();
        });
    }

    public function clearCache(): void
    {
        Cache::forget('planning_progress_dist:preview');
        Cache::forget('planning_progress_dist:real');
    }

    private function totalIncome(User $user): float
    {
        return (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;
    }
}
