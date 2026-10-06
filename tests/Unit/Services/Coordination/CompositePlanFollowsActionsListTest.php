<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Coordination\CompositePlanService;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * The Holistic Plan lists the actions list's open items in the actions list's
 * order (CSJ 2026-10-01, one list on every surface; CSJ 2026-09-09, the seeded
 * priority wins), not its own pound-first ranking, and an action marked done
 * leaves both.
 */
beforeEach(function () {
    $this->seed([
        TaxConfigurationSeeder::class,
        TierConfigurationSeeder::class,
        TaxActionDefinitionSeeder::class,
        RetirementActionDefinitionSeeder::class,
        SavingsActionDefinitionSeeder::class,
        InvestmentActionDefinitionSeeder::class,
        ProtectionActionDefinitionSeeder::class,
        EstateActionDefinitionSeeder::class,
    ]);
    Artisan::call('db:seed', ['--class' => 'PreviewUserSeeder']);
});

function planIds(User $user): array
{
    return array_column(app(CompositePlanService::class)->compose($user->fresh())['items'], 'id');
}

function openActionIds(User $user, array $ids): array
{
    return array_values(array_intersect(array_column(app(NextActionsService::class)->buildAll($user->id), 'id'), $ids));
}

it('lists the plan in the actions list\'s order and drops an action marked done', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();

    $ids = planIds($user);
    expect(count($ids))->toBeGreaterThan(5)
        ->and($ids)->toBe(openActionIds($user, $ids));

    // A demo completion belongs to the visitor's token (#1087), so mark it as one.
    $user->withAccessToken($user->createToken('preview-access')->accessToken);
    Auth::setUser($user);

    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => $ids[0],
        'module' => 'general',
        'recommendation_text' => 'done',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    expect(planIds($user))->not->toContain($ids[0]);
});
