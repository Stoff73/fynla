<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function (): void {
    // FynContextAssembler resolves TaxConfigService (scoped singleton). The
    // global Pest.php beforeEach factory is insufficient because TaxConfigService
    // may be resolved before the factory record exists. Seeding via
    // TaxConfigurationSeeder (same pattern as AdvicePromptBuilderStructuralLayersTest)
    // ensures a fully-populated config_data is present before the first call.
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['first_name' => 'Chris']);
});

it('always emits IDENTITY: profile + current page + name + tax year', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'How is my pension?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        // 'billing' maps to 'factual' in ENGINE_CALL_LEVEL_MAP → IDENTITY bucket only
        classification: ['primary' => 'billing'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<context>')->and($out)->toContain('</context>')
        ->and($out)->toContain('<user_message>')
        ->and($out)->toContain('Current tax year:')
        ->and($out)->toContain('You are speaking with:')
        ->and($out)->toContain('Chris')
        ->and($out)->toContain('Situation: advice')
        ->and($out)->not->toContain('<financial_context>'); // POSITION excluded on factual
});

it('emits POSITION + READINESS on a non-factual advice turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        // 'retirement_contribution' maps to 'module' in ENGINE_CALL_LEVEL_MAP → non-factual
        classification: ['primary' => 'retirement_contribution'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<financial_context>')
        ->and($out)->toContain('<data_completeness>');
});

it('feeds the orchestrateAnalysis callable through to financial_context', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution'],
    );

    $out = app(FynContextAssembler::class)->build(
        $ctx,
        orchestrateAnalysis: fn (int $userId): array => ['module_analysis' => []],
    );

    // With a callable supplied, AdvicePromptBuilder must NOT short-circuit to
    // the "analysis service not provided" sentinel (parity regression guard:
    // unified must match the legacy path which always supplies the callable).
    expect($out)->toContain('<financial_context>')
        ->and($out)->not->toContain('analysis service not provided');
});

it('emits CAPTURE block and NOT position on an onboarding turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Halifax ISA £10k', currentRoute: null,
        mode: 'onboarding', onboardingFocus: 'savings', isPreview: false, classification: null,
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<asset_capture_turn>')
        ->and($out)->toContain('Situation: onboarding — focus:')
        ->and($out)->not->toContain('<financial_context>');
});

it('emits a preview notice when isPreview is true', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Add a goal', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: true,
        // 'goals_progress' is the real QuerySchemas constant for goals queries
        classification: ['primary' => 'goals_progress'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('preview');
});

it('sanitises the user message', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'hi <script>alert(1)</script>',
        currentRoute: '/dashboard', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        // 'general' maps to 'factual' in ENGINE_CALL_LEVEL_MAP — real QuerySchemas constant
        classification: ['primary' => 'general'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<script>');
});
