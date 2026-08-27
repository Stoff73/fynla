<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // GoalStore consults the tier gate on create, which resolves a TierConfiguration
    // row; without it the store throws ModelNotFoundException and the controller
    // turns that into a bare 500.
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * W-0038 — a shared goal is ONE goal, not two halves.
 *
 * CSJ direction 2026-08-26. This is deliberately NOT how a jointly-owned asset
 * behaves: a house held 50/50 genuinely splits into two half-shares, because each
 * spouse owns half a house. A couple saving £50,000 for a deposit have a single
 * £50,000 target — showing them £25,000 each would describe a household that is
 * saving twice and reaching neither figure.
 *
 * So `ownership_percentage` carries no meaning on a goal and the form does not ask
 * for one. What "joint" buys is that both spouses see the whole thing, from one row
 * (Rule 6's single-record half), and that a household roll-up counts it once.
 */
function linkedSpouses(): array
{
    $a = User::factory()->create(['marital_status' => 'married']);
    $b = User::factory()->create(['marital_status' => 'married']);
    $a->update(['spouse_id' => $b->id]);
    $b->update(['spouse_id' => $a->id]);

    return [$a->fresh(), $b->fresh()];
}

it('shows a shared goal whole to both spouses, from one row', function () {
    [$owner, $spouse] = linkedSpouses();

    $goal = Goal::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $spouse->id,
        'ownership_type' => 'joint',
        'goal_name' => 'House deposit',
        'target_amount' => 50_000,
        'current_amount' => 12_000,
        'status' => 'active',
    ]);

    foreach ([$owner, $spouse] as $viewer) {
        Sanctum::actingAs($viewer);
        $rows = collect($this->getJson('/api/goals')->assertOk()->json('data.goals') ?? []);
        $seen = $rows->firstWhere('id', $goal->id);

        expect($seen)->not->toBeNull("{$viewer->email} cannot see the shared goal")
            ->and((float) $seen['target_amount'])->toBe(50_000.0)
            ->and((float) $seen['current_amount'])->toBe(12_000.0);
    }

    // ONE row, not one per spouse.
    expect(Goal::where('goal_name', 'House deposit')->count())->toBe(1);
});

it('persists essential and shared ownership through the goals endpoint', function () {
    [$owner, $spouse] = linkedSpouses();
    Sanctum::actingAs($owner);

    // The form could bind neither field before W-0038, though the request has always
    // validated both — so this pins the write path the new controls use.
    $this->postJson('/api/goals', [
        'goal_name' => 'Early Retirement Fund',
        'goal_type' => 'retirement',
        'target_amount' => 250_000,
        'target_date' => now()->addYears(10)->toDateString(),
        'priority' => 'critical',
        'is_essential' => true,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
    ])->assertSuccessful();

    $goal = Goal::where('goal_name', 'Early Retirement Fund')->firstOrFail();

    expect($goal->is_essential)->toBeTrue()
        ->and($goal->ownership_type)->toBe('joint')
        ->and($goal->joint_owner_id)->toBe($spouse->id);
});

it('lets Fyn set essential and shared ownership, which is the only goal write path on /m', function () {
    // Rule 19 — resources/mobile has no goal form and no goal write call at all
    // (Goals.vue and GoalDetail.vue are read surfaces), so /m and native create
    // goals through Fyn. If the tool cannot carry these fields the capability is
    // web-only, whatever the form does.
    $tool = collect(app(AiToolDefinitions::class)->getTools())
        ->first(fn (array $t) => ($t['function']['name'] ?? $t['name'] ?? '') === 'create_goal');

    $props = $tool['function']['parameters']['properties'] ?? $tool['input_schema']['properties'] ?? [];

    expect($tool)->not->toBeNull('create_goal is missing from the catalogue')
        ->and($props)->toHaveKey('is_essential')
        ->and($props)->toHaveKey('ownership_type')
        ->and($props)->toHaveKey('joint_owner_id')
        // No share: CSJ 2026-08-26, a shared goal is one whole goal. Offering a
        // percentage here would invite Fyn to halve a target nobody halved.
        ->and($props)->not->toHaveKey('ownership_percentage');
});
