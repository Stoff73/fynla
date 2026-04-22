<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Prompts\DataCapturePromptBuilder;
use App\ValueObjects\CaptureContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pin the DataCapturePromptBuilder's structural invariants — the things
 * the rest of the persona-split system depends on being present in every
 * capture-turn system prompt.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->builder = app(DataCapturePromptBuilder::class);
});

it('includes the capture_complete handoff instruction', function () {
    $user = User::factory()->create(['first_name' => 'Chris', 'surname' => 'Jones']);
    $context = new CaptureContext(
        reason: 'SIPP record needed',
        entityTypes: ['pension'],
    );

    $prompt = $this->builder->build($user, $context);

    expect($prompt)->toContain('capture_complete');
    expect($prompt)->toContain('summary: one-sentence user-facing recap');
    expect($prompt)->toContain('records_created');
});

it('includes the MULTI-ENTITY RULE block', function () {
    $user = User::factory()->create();
    $context = new CaptureContext(reason: 'r', entityTypes: ['savings']);

    $prompt = $this->builder->build($user, $context);

    expect($prompt)->toContain('MULTI-ENTITY RULE');
    expect($prompt)->toContain('ONE');
    expect($prompt)->toContain('tool_use block PER record');
});

it('includes the pending advice question when provided', function () {
    $user = User::factory()->create();
    $context = new CaptureContext(
        reason: 'SIPP needed for retirement projection',
        entityTypes: ['pension'],
        pendingAdviceQuestion: 'Is my retirement on track?',
    );

    $prompt = $this->builder->build($user, $context);

    expect($prompt)->toContain('Is my retirement on track?');
    expect($prompt)->toContain('re-prime advice Fyn');
});

it('omits the pending-question bridge when no advice question was supplied', function () {
    $user = User::factory()->create();
    $context = new CaptureContext(reason: 'inline', entityTypes: ['savings']);

    $prompt = $this->builder->build($user, $context);

    expect($prompt)->not->toContain('re-prime advice Fyn');
    expect($prompt)->toContain('inline data capture');
});

it('lists fields_needed prominently when provided', function () {
    $user = User::factory()->create();
    $context = new CaptureContext(
        reason: 'r',
        entityTypes: ['pension'],
        fieldsNeeded: ['scheme_name', 'current_fund_value', 'provider'],
    );

    $prompt = $this->builder->build($user, $context);

    expect($prompt)->toContain('scheme_name, current_fund_value, provider');
    expect($prompt)->toContain('Prioritise capturing them');
});

it('forbids delegate_to_capture (the inverse handoff)', function () {
    $user = User::factory()->create();
    $context = new CaptureContext(reason: 'r', entityTypes: ['savings']);

    $prompt = $this->builder->build($user, $context);

    // Data-capture Fyn MUST NOT emit delegate_to_capture — that would
    // send control back to advice Fyn mid-capture and cause a ping-pong.
    expect($prompt)->toContain('Do NOT emit `delegate_to_capture`');
});

it('enforces the one-sentence guardrail on ack text', function () {
    $user = User::factory()->create();
    $context = new CaptureContext(reason: 'r', entityTypes: ['savings']);

    $prompt = $this->builder->build($user, $context);

    // The B-9 post-stream truncator is the enforcement. The prompt-level
    // guardrail must still be present as the first line of defence.
    expect($prompt)->toContain('EXACTLY ONE short sentence per capture');
});
