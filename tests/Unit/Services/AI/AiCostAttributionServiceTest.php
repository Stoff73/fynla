<?php

declare(strict_types=1);

use App\Models\AiCostAttribution;
use App\Services\AI\Cost\AiCostAttributionService;

/**
 * CoALA Phase 5 item 8 (FR-M11) increment 1 — the cost-attribution ledger
 * storage: the service write path, model casts, and column defaults.
 */
it('records a cost-attribution row, applying defaults for omitted fields', function () {
    $row = app(AiCostAttributionService::class)->record([
        'stage' => 'planner',
        'model' => 'grok-4.3',
        'session_mode' => 'advice',
        'action_type' => 'reason',
        'output_tokens' => 12,
        'gbp_cost' => 0.0034,
        'gbp_cost_priced' => true,
    ]);

    expect($row->exists)->toBeTrue()
        ->and($row->stage)->toBe('planner')
        ->and($row->session_mode)->toBe('advice')
        ->and($row->action_type)->toBe('reason')
        ->and($row->output_tokens)->toBe(12)
        ->and($row->cycle_id)->toBe(1)          // default
        ->and($row->input_tokens)->toBe(0)      // default
        ->and($row->cache_hit_tokens)->toBe(0)  // default
        ->and($row->gbp_cost_priced)->toBeTrue();

    $this->assertDatabaseHas('ai_cost_attribution', [
        'id' => $row->id,
        'stage' => 'planner',
        'action_type' => 'reason',
        'gbp_cost_priced' => true,
    ]);
});

it('casts numeric and boolean columns', function () {
    $row = app(AiCostAttributionService::class)->record([
        'stage' => 'reasoner',
        'model' => 'claude-haiku-4-5-20251001',
        'cycle_id' => 3,
        'input_tokens' => 100,
        'cache_hit_tokens' => 40,
        'gbp_cost_priced' => false,
    ])->fresh();

    expect($row->cycle_id)->toBe(3)
        ->and($row->input_tokens)->toBe(100)
        ->and($row->cache_hit_tokens)->toBe(40)
        ->and($row->gbp_cost_priced)->toBeFalse();
});

it('persists an attribution unattached to a user or conversation (system call)', function () {
    $row = app(AiCostAttributionService::class)->record([
        'stage' => 'planner',
        'model' => 'grok-4.3',
    ]);

    expect($row->user_id)->toBeNull()
        ->and($row->conversation_id)->toBeNull()
        ->and(AiCostAttribution::count())->toBe(1);
});
