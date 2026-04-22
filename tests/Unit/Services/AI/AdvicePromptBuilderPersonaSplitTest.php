<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B-8 — advice Fyn was preferring navigate_to_page + fill_form instead of
 * the persona-split `delegate_to_capture` handoff. The fix is a new
 * <persona_split_handoff> prompt block that explicitly biases the model
 * toward delegate_to_capture for inline record capture. These tests pin
 * the prompt's structure so the bias cannot silently regress.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('includes the persona-split handoff block when the flag is enabled', function () {
    config(['fyn.persona_split_enabled' => true]);
    $user = User::factory()->create(['is_preview_user' => false]);

    $prompt = app(AdvicePromptBuilder::class)->build(
        user: $user,
        classification: null,
        kycResult: null,
        currentRoute: null,
        isPreview: false,
    );

    expect($prompt)->toContain('<persona_split_handoff>');
    expect($prompt)->toContain('delegate_to_capture');
    expect($prompt)->toContain('preferred over navigate_to_page');
    // The specific session-3 example that motivated B-8.
    expect($prompt)->toContain('add my SIPP');
});

it('omits the persona-split handoff block when the flag is disabled', function () {
    config(['fyn.persona_split_enabled' => false]);
    $user = User::factory()->create(['is_preview_user' => false]);

    $prompt = app(AdvicePromptBuilder::class)->build(
        user: $user,
        classification: null,
        kycResult: null,
        currentRoute: null,
        isPreview: false,
    );

    expect($prompt)->not->toContain('<persona_split_handoff>');
});

it('omits the persona-split handoff block in preview mode so the preview block takes precedence', function () {
    config(['fyn.persona_split_enabled' => true]);
    $user = User::factory()->create(['is_preview_user' => true]);

    $prompt = app(AdvicePromptBuilder::class)->build(
        user: $user,
        classification: null,
        kycResult: null,
        currentRoute: null,
        isPreview: true,
    );

    // Preview block must be there (existing behaviour — must not regress).
    expect($prompt)->toContain('<preview_mode>');
    expect($prompt)->toContain('NEVER emit the internal `delegate_to_capture`');

    // Persona-split bias MUST NOT render in preview — if it did, it would
    // contradict the preview block's instruction to NEVER delegate.
    expect($prompt)->not->toContain('<persona_split_handoff>');
});
