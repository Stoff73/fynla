<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * WP-2 — one actions model. GET /api/recommendations/actions is the single
 * payload for the "all my actions" surfaces: `open` = the same ranked list
 * the dashboards consume (stable aggregator ids, uncapped) and `completed` =
 * the recommendation_tracking history that previously had no home.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('returns open and completed actions in one payload', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => 'tax_isa_topup_vs_psa',
        'module' => 'tax',
        'recommendation_text' => 'Move savings into your ISA',
        'status' => 'completed',
        'completed_at' => now()->subDay(),
    ]);

    $response = $this->getJson('/api/recommendations/actions');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['open', 'completed']]);

    $completed = $response->json('data.completed');
    expect($completed)->toHaveCount(1)
        ->and($completed[0]['recommendation_id'])->toBe('tax_isa_topup_vs_psa');
});

it('buildAll returns every open action uncapped while build stays capped at four', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $service = app(NextActionsService::class);
    $capped = $service->build($user->id);
    $all = $service->buildAll($user->id);

    expect(count($capped))->toBeLessThanOrEqual(4)
        ->and(count($all))->toBeGreaterThanOrEqual(count($capped));

    // Same ranking head: the capped list is a prefix of the full list.
    expect(array_column(array_slice($all, 0, count($capped)), 'id'))
        ->toBe(array_column($capped, 'id'));
});

it('stamps tax strategy items with stable aggregator ids and completion state', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 120000, // 60% trap → pa_taper_rescue fires
        'date_of_birth' => '1985-01-12',
        'marital_status' => 'single',
        'household_calculation_mode' => 'single',
    ]);
    $this->seed(TaxActionDefinitionSeeder::class);
    Sanctum::actingAs($user);

    $first = $this->getJson('/api/tax-strategy')->assertOk()->json('data');
    $items = $first['composed_plan']['items'] ?? [];
    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item['recommendation_id'])->toBe('tax_'.$item['type'])
            ->and($item['completed'])->toBeFalse();
    }

    // Mark the first one done through the shared endpoint…
    $target = $items[0];
    $this->postJson("/api/recommendations/{$target['recommendation_id']}/mark-done", [
        'module' => 'tax',
        'recommendation_text' => $target['title'],
    ])->assertOk();

    // …and the tax payload reflects it on the next fetch.
    $second = $this->getJson('/api/tax-strategy')->assertOk()->json('data');
    $updated = collect($second['composed_plan']['items'])
        ->firstWhere('recommendation_id', $target['recommendation_id']);

    expect($updated['completed'])->toBeTrue()
        ->and($updated['completed_at'])->not->toBeNull();
});

it('includes completed actions in the mobile achievements payload', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => 'protection_abc',
        'module' => 'protection',
        'recommendation_text' => 'Add critical illness cover',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/mobile/achievements');

    $response->assertOk();
    $completed = $response->json('data.completed');
    expect($completed)->toHaveCount(1)
        ->and($completed[0]['title'])->toBe('Add critical illness cover')
        ->and($completed[0]['module'])->toBe('protection');
});
