<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AdviceResponseComposer;
use App\Services\AI\AdviceResponseSchema;
use App\Services\AI\AdviceResponseSchemaException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S1.6 — `advice_response` SSE event payload shape contract.
 *
 * INV-2.3.5 — recommendation-mode turns emit exactly one
 * `advice_response` event with a strictly-typed shape composed from
 * `orchestrateAnalysis` engine output. Factual / out-of-remit / billing
 * turns emit zero. Schema is enforced — the composer throws
 * AdviceResponseSchemaException on any structural drift.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->composer = app(AdviceResponseComposer::class);
});

it('schema accepts a minimal valid payload (all required keys present, arrays may be empty)', function () {
    AdviceResponseSchema::validate([
        'type' => 'advice_response',
        'headline' => 'Test headline',
        'key_figures' => [],
        'breakdowns' => [],
        'recommendations' => [],
        'next_steps' => [],
        'signposting' => 'For regulated advice…',
    ]);

    expect(true)->toBeTrue();
});

it('schema rejects a payload missing required keys', function () {
    AdviceResponseSchema::validate([
        'type' => 'advice_response',
        'headline' => 'Test',
        // missing key_figures, breakdowns, recommendations, next_steps, signposting
    ]);
})->throws(AdviceResponseSchemaException::class, 'Missing required key');

it('schema rejects empty headline', function () {
    AdviceResponseSchema::validate([
        'type' => 'advice_response',
        'headline' => '',
        'key_figures' => [],
        'breakdowns' => [],
        'recommendations' => [],
        'next_steps' => [],
        'signposting' => 'For regulated advice…',
    ]);
})->throws(AdviceResponseSchemaException::class, 'headline');

it('schema rejects an unknown priority on a recommendation', function () {
    AdviceResponseSchema::validate([
        'type' => 'advice_response',
        'headline' => 'Test',
        'key_figures' => [],
        'breakdowns' => [],
        'recommendations' => [
            ['title' => 'Do thing', 'priority' => 'extreme', 'reason' => 'because'],
        ],
        'next_steps' => [],
        'signposting' => 'For regulated advice…',
    ]);
})->throws(AdviceResponseSchemaException::class, 'priority');

it('schema rejects an unexpected field on a key_figure', function () {
    AdviceResponseSchema::validate([
        'type' => 'advice_response',
        'headline' => 'Test',
        'key_figures' => [
            ['label' => 'Surplus', 'value' => '£1,500', 'extra' => 'banned'],
        ],
        'breakdowns' => [],
        'recommendations' => [],
        'next_steps' => [],
        'signposting' => 'For regulated advice…',
    ]);
})->throws(AdviceResponseSchemaException::class, 'unexpected field');

it('composer produces a schema-valid payload from empty engine output', function () {
    $user = User::factory()->create();
    $payload = $this->composer->compose($user, [
        'summary' => ['total_recommendations' => 0],
        'available_surplus' => 0,
        'ranked_recommendations' => [],
        'module_analysis' => [],
    ], ['primary' => 'protection_cover']);

    expect($payload['type'])->toBe('advice_response');
    expect($payload['headline'])->toContain('Protection cover');
    expect($payload['recommendations'])->toBe([]);
    expect($payload['signposting'])->toBe(AdviceResponseSchema::FCA_SIGNPOSTING);
});

it('composer surfaces ranked_recommendations into the recommendations array (capped at 5)', function () {
    $user = User::factory()->create();

    $ranked = [];
    for ($i = 0; $i < 8; $i++) {
        $ranked[] = [
            'title' => "Rec {$i}",
            'priority' => $i < 3 ? 'high' : 'medium',
            'reason' => "reason {$i}",
        ];
    }

    $payload = $this->composer->compose($user, [
        'summary' => ['total_recommendations' => 8],
        'ranked_recommendations' => $ranked,
        'module_analysis' => [],
    ], ['primary' => 'protection_cover']);

    expect($payload['recommendations'])->toHaveCount(5);
    expect($payload['recommendations'][0]['priority'])->toBe('high');
});

it('composer normalises numeric and string priorities to the schema-allowed enum', function () {
    $user = User::factory()->create();

    $payload = $this->composer->compose($user, [
        'summary' => ['total_recommendations' => 4],
        'ranked_recommendations' => [
            ['title' => 'Numeric high', 'priority' => 9, 'reason' => 'r'],
            ['title' => 'Numeric mid', 'priority' => 6, 'reason' => 'r'],
            ['title' => 'Urgent string', 'priority' => 'urgent', 'reason' => 'r'],
            ['title' => 'Unknown', 'priority' => 'mystery', 'reason' => 'r'],
        ],
        'module_analysis' => [],
    ], ['primary' => 'protection_cover']);

    expect($payload['recommendations'][0]['priority'])->toBe('high');
    expect($payload['recommendations'][1]['priority'])->toBe('medium');
    expect($payload['recommendations'][2]['priority'])->toBe('high');
    expect($payload['recommendations'][3]['priority'])->toBe('info');
});

it('composer pulls per-module headline metric into breakdowns', function () {
    $user = User::factory()->create();

    $payload = $this->composer->compose($user, [
        'summary' => ['total_recommendations' => 0],
        'ranked_recommendations' => [],
        'module_analysis' => [
            'protection' => ['data' => ['total_cover' => 250000]],
            'savings' => ['data' => ['total_savings' => 35000]],
        ],
    ], ['primary' => 'holistic_health']);

    $labels = array_column($payload['breakdowns'], 'label');
    expect($labels)->toContain('Protection');
    expect($labels)->toContain('Savings');
});

it('composer surfaces shortfall when the engine flags one', function () {
    $user = User::factory()->create();

    $payload = $this->composer->compose($user, [
        'summary' => ['total_recommendations' => 1],
        'ranked_recommendations' => [
            ['title' => 'Top up', 'priority' => 'medium', 'reason' => 'r'],
        ],
        'module_analysis' => [],
        'shortfall_analysis' => ['has_shortfall' => true, 'shortfall_amount' => 240],
    ], ['primary' => 'holistic_health']);

    $labels = array_column($payload['breakdowns'], 'label');
    expect($labels)->toContain('Cashflow shortfall');
});

it('composer extracts unique route_paths into next_steps (deduped + capped at 5)', function () {
    $user = User::factory()->create();

    $payload = $this->composer->compose($user, [
        'summary' => ['total_recommendations' => 4],
        'ranked_recommendations' => [
            ['title' => 'A', 'priority' => 'high', 'reason' => 'r', 'route_path' => '/protection'],
            ['title' => 'B', 'priority' => 'medium', 'reason' => 'r', 'route_path' => '/protection'], // dup
            ['title' => 'C', 'priority' => 'medium', 'reason' => 'r', 'route_path' => '/savings'],
            ['title' => 'D', 'priority' => 'low', 'reason' => 'r'], // no route
        ],
        'module_analysis' => [],
    ], ['primary' => 'protection_cover']);

    expect($payload['next_steps'])->toHaveCount(2);
    $routes = array_column($payload['next_steps'], 'route_path');
    expect($routes)->toBe(['/protection', '/savings']);
});
