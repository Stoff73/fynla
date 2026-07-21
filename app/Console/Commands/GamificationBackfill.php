<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\ExpenditureProfile;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Property;
use App\Models\RecommendationTracking;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserMilestone;
use App\Services\Gamification\PointsService;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Console\Command;

/**
 * One-time reconciliation: award points for data, income, completed
 * recommendations and already-crossed milestones that existing users
 * accumulated before the engine launched.
 *
 * Idempotent — every award uses the SAME dedup key as the live award path, so
 * re-running awards nothing more. Quiet — pending_celebration_level is cleared
 * per user so existing users land at their earned level without a celebration.
 * Logins/streaks are intentionally NOT backfilled (can't reconstruct fairly).
 */
class GamificationBackfill extends Command
{
    protected $signature = 'gamification:backfill';

    protected $description = 'Quiet, idempotent one-time award reconciliation for existing users (data, income, recommendations, milestones).';

    /** @var array<class-string, string> model => gamification category */
    private const DATA_MODELS = [
        SavingsAccount::class => 'savings_account',
        InvestmentAccount::class => 'investment_account',
        Property::class => 'property',
        DCPension::class => 'pension',
        DBPension::class => 'pension',
        LifeInsurancePolicy::class => 'protection_policy',
        CriticalIllnessPolicy::class => 'protection_policy',
        IncomeProtectionPolicy::class => 'protection_policy',
        DisabilityPolicy::class => 'protection_policy',
        SicknessIllnessPolicy::class => 'protection_policy',
        Goal::class => 'goal',
        LastingPowerOfAttorney::class => 'estate',
        ExpenditureProfile::class => 'expenditure',
    ];

    public function handle(PointsService $points, UserProfileService $profiles): int
    {
        $users = 0;

        User::query()->where('is_preview_user', false)->chunkById(200, function ($chunk) use ($points, $profiles, &$users) {
            foreach ($chunk as $user) {
                // 1. Data records — first-in-category + capped extras, by owning user_id
                //    (matches the created-observer, which fires for $model->user_id).
                foreach (self::DATA_MODELS as $model => $category) {
                    $model::query()
                        ->where('user_id', $user->id)
                        ->orderBy('id')
                        ->get()
                        ->each(fn ($record) => $points->awardDataEntry($user, $category, (int) $record->getKey()));
                }

                // 2. Income first-capture (same "is set" test as the live award).
                if ($profiles->totalGrossAnnualIncome($user) > 0) {
                    $points->award($user, 'data', 'data:income:first',
                        (int) config('gamification.points.data_first_in_category'), ['category' => 'income']);
                }

                // 3. Completed recommendations.
                RecommendationTracking::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->get()
                    ->each(fn ($rec) => $points->award($user, 'recommendation',
                        "recommendation:{$rec->recommendation_id}",
                        (int) config('gamification.points.recommendation'), ['module' => $rec->module]));

                // 4. Already-crossed milestones (keys match MilestoneDetectionService exactly).
                UserMilestone::where('user_id', $user->id)->get()->each(function ($m) use ($points, $user) {
                    $ref = $m->reference_id ?? 0;
                    $points->award($user, 'milestone',
                        "milestone:{$m->milestone_type}:{$ref}:".(int) $m->threshold,
                        (int) config('gamification.points.milestone'),
                        ['threshold' => (int) $m->threshold]);
                });

                // 5. Quiet: suppress any celebration the awards queued.
                UserGamification::where('user_id', $user->id)->update(['pending_celebration_level' => null]);

                $users++;
            }
        });

        $this->info("Gamification backfill complete. Processed {$users} non-preview users.");

        return self::SUCCESS;
    }
}
