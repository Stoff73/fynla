<?php

declare(strict_types=1);

namespace Database\Seeders;

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
use App\Models\PointAward;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\LevelService;
use Illuminate\Database\Seeder;

/**
 * Seeds gamification state for preview personas DIRECTLY (PointsService no-ops
 * for preview users by design). Awards are derived from each persona's real
 * seeded records — first-in-category plus capped extras, mirroring
 * PointsService::awardDataEntry — so the /m wheel level, planning-progress
 * percentile, and achievements data-badges reflect each persona's actual data
 * and differ persona-to-persona. Idempotent (updateOrCreate on dedup_key).
 */
class PreviewGamificationSeeder extends Seeder
{
    /** @var array<class-string, string> model => gamification category (mirrors GamificationBackfill::DATA_MODELS) */
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

    public function run(): void
    {
        $levels = app(LevelService::class);
        $profiles = app(UserProfileService::class);
        $cfg = config('gamification.points');
        $first = (int) $cfg['data_first_in_category'];
        $extra = (int) $cfg['data_extra_record'];
        $cap = (int) $cfg['data_extra_cap_per_category'];

        User::where('is_preview_user', true)->get()->each(function (User $user) use ($levels, $first, $extra, $cap) {
            $points = 0;

            // Count records per category across all models that map to it.
            $countByCategory = [];
            foreach (self::DATA_MODELS as $model => $category) {
                $n = $model::query()->where('user_id', $user->id)->count();
                if ($n > 0) {
                    $countByCategory[$category] = ($countByCategory[$category] ?? 0) + $n;
                }
            }

            // First-in-category (20) + capped extras (5 each, up to cap) per category.
            foreach ($countByCategory as $category => $n) {
                PointAward::updateOrCreate(
                    ['user_id' => $user->id, 'dedup_key' => "data:{$category}:first"],
                    ['source_type' => 'data', 'points' => $first, 'meta' => ['category' => $category, 'seeded' => true]],
                );
                $points += $first;

                $extras = min(max($n - 1, 0), $cap);
                for ($i = 0; $i < $extras; $i++) {
                    PointAward::updateOrCreate(
                        ['user_id' => $user->id, 'dedup_key' => "data:{$category}:rec:seed{$i}"],
                        ['source_type' => 'data', 'points' => $extra, 'meta' => ['category' => $category, 'seeded' => true]],
                    );
                    $points += $extra;
                }
            }

            // Income first-capture if the persona has any income.
            if ($profiles->totalGrossAnnualIncome($user) > 0) {
                PointAward::updateOrCreate(
                    ['user_id' => $user->id, 'dedup_key' => 'data:income:first'],
                    ['source_type' => 'data', 'points' => $first, 'meta' => ['category' => 'income', 'seeded' => true]],
                );
                $points += $first;
            }

            UserGamification::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'total_points' => $points,
                    'level' => $levels->levelForPoints($points),
                    'login_streak_days' => $points >= 150 ? 7 : ($points >= 80 ? 3 : 1),
                    'pending_celebration_level' => null,
                ],
            );
        });
    }
}
