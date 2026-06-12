<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Database\Seeders\TaxConfigurationSeeder;

// Parity restoration guard. The unified cutover (default since 2026-05-17) dropped
// FinancialPlanningKnowledge injection and the legacy voicing / claim-tier rules with
// no per-turn replacement. Same regression family as the billing layer (#335). These
// tests pin the restored layers so the same regression cannot silently recur.

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['first_name' => 'Chris']);
});

it('injects financial knowledge and voicing rules on advice turns', function (): void {
    // 'retirement_contribution' maps to KNOWLEDGE_DOMAINS containing
    // getPensionKnowledge, getIncomeClassifications, getAffordabilityRules — a
    // non-empty domain set that exercises both the knowledge block and voicing layer.
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution'],
    );

    $out = app(FynContextAssembler::class)->build($ctx, fn () => []);

    expect($out)
        ->toContain('<financial_knowledge>')
        ->toContain('AFFORDABILITY')
        ->toContain('<voicing_rules>')
        ->toContain('MECHANICAL claims');
});

it('omits knowledge and voicing on onboarding turns', function (): void {
    // Onboarding mode — neither block should be emitted. The capture turn
    // instructions replace them. Mirrors the billing layer suppression pattern.
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Halifax ISA £10k',
        currentRoute: null,
        mode: 'onboarding',
        onboardingFocus: 'savings',
        isPreview: false,
        classification: null,
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)
        ->not->toContain('<financial_knowledge>')
        ->and($out)->not->toContain('<voicing_rules>');
});

it('omits the knowledge block when the classification yields no domain content but keeps voicing rules', function (): void {
    // 'billing' is a FACTUAL_TYPE — not a bypass type, not GENERAL, not HOLISTIC_HEALTH.
    // QueryKnowledge::KNOWLEDGE_DOMAINS has no 'billing' key → $domains = [] → returns ''.
    // The voicing layer must still appear on this non-onboarding advice turn.
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: "Where's my invoice?",
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'billing'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)
        ->not->toContain('<financial_knowledge>')
        ->and($out)->toContain('<voicing_rules>')
        ->and($out)->toContain('MECHANICAL claims');
});
