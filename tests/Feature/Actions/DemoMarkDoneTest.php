<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserGamification;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

/**
 * A demo persona is one account shared by every demo visitor, each on their
 * own token. "Mark as done" in a demo is kept for that visitor's token only:
 * the card and the actions list show it done for them, no other visitor sees
 * it, the shared persona earns no points, and it goes when the token does.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function demoMarkDone($test, string $token, string $id)
{
    app('auth')->forgetGuards();

    return $test->withToken($token)->postJson('/api/recommendations/'.rawurlencode($id).'/mark-done', [
        'module' => 'savings',
        'recommendation_text' => 'Build your emergency fund',
    ]);
}

function demoCompletedIds($test, string $token): array
{
    app('auth')->forgetGuards();

    return collect($test->withToken($token)->getJson('/api/recommendations/actions')->assertOk()->json('data.completed'))
        ->pluck('recommendation_id')
        ->all();
}

it('keeps a demo visitor\'s Mark as done for that visitor only', function () {
    $persona = User::factory()->preview()->create();
    $visitorA = $persona->createToken('preview-access')->plainTextToken;
    $visitorB = $persona->createToken('preview-access')->plainTextToken;

    demoMarkDone($this, $visitorA, 'savings:emergency_fund')
        ->assertOk()
        ->assertJsonMissingPath('preview_mode');

    expect(demoCompletedIds($this, $visitorA))->toBe(['savings:emergency_fund'])
        ->and(demoCompletedIds($this, $visitorB))->toBe([]);

    app('auth')->forgetGuards();
    expect(RecommendationTracking::where('user_id', $persona->id)->count())->toBe(0, 'outside the demo session no demo row is seen');
});

it('awards the shared persona no points for a demo completion', function () {
    $persona = User::factory()->preview()->create();
    $visitor = $persona->createToken('preview-access')->plainTextToken;

    demoMarkDone($this, $visitor, 'savings:emergency_fund')->assertOk();

    expect(demoCompletedIds($this, $visitor))->toBe(['savings:emergency_fund'])
        ->and((int) (UserGamification::where('user_id', $persona->id)->value('total_points') ?? 0))->toBe(0);
});

it('deletes a demo visitor\'s completions with their token', function () {
    $persona = User::factory()->preview()->create();
    $visitor = $persona->createToken('preview-access')->plainTextToken;

    demoMarkDone($this, $visitor, 'savings:emergency_fund')->assertOk();
    expect(RecommendationTracking::withoutGlobalScopes()->where('user_id', $persona->id)->count())->toBe(1);

    PersonalAccessToken::findToken($visitor)->delete();

    expect(RecommendationTracking::withoutGlobalScopes()->where('user_id', $persona->id)->count())->toBe(0);
});

it('leaves a real user\'s Mark as done as before', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $token = $user->createToken('auth')->plainTextToken;

    demoMarkDone($this, $token, 'savings:emergency_fund')->assertOk();

    $row = RecommendationTracking::withoutGlobalScopes()->where('user_id', $user->id)->sole();
    expect($row->preview_token_id)->toBeNull()
        ->and($row->status)->toBe('completed')
        ->and(demoCompletedIds($this, $token))->toBe(['savings:emergency_fund'])
        ->and((int) UserGamification::where('user_id', $user->id)->value('total_points'))->toBeGreaterThan(0);
});

it('refuses a demo row with no visitor token', function () {
    $persona = User::factory()->preview()->create();

    $row = RecommendationTracking::create([
        'user_id' => $persona->id,
        'recommendation_id' => 'savings:emergency_fund',
        'module' => 'savings',
        'recommendation_text' => 'Build your emergency fund',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    expect($row->exists)->toBeFalse()
        ->and(RecommendationTracking::withoutGlobalScopes()->where('user_id', $persona->id)->count())->toBe(0);
});

it('carries a demo visitor\'s completions across the /m token swap', function () {
    $persona = User::factory()->preview()->create();
    $visitor = $persona->createToken('preview-access')->plainTextToken;

    demoMarkDone($this, $visitor, 'savings:emergency_fund')->assertOk();

    app('auth')->forgetGuards();
    $swapped = $this->withToken($visitor)->postJson('/api/v1/auth/refresh-token')->assertOk()->json('data.token');

    expect(PersonalAccessToken::findToken($visitor))->toBeNull()
        ->and(demoCompletedIds($this, $swapped))->toBe(['savings:emergency_fund']);
});
