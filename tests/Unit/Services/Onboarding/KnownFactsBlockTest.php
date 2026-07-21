<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\Onboarding\OnboardingPromptBuilder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S1.4 — `<known_facts>` block injection. Verifies that every prompt
 * surface that the plan touches (asset_capture, grouped_extract,
 * advice) carries the structured fact map AND the canonical
 * "Do not ask the user for any field above." suffix (INV-2.2.3).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('OnboardingPromptBuilder::buildAssetCapturePrompt injects <known_facts> with every authoritative field name', function () {
    $user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'User',
        'date_of_birth' => '1985-04-14',
        'marital_status' => 'married',
        'employment_status' => 'employed',
        'annual_employment_income' => 50000.00,
        'monthly_expenditure' => 2500.00,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Test',
        'status' => 'active',
        'model_used' => 'grok-4.3',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);

    $prompt = app(OnboardingPromptBuilder::class)->buildAssetCapturePrompt($user, 'protection', $conversation);

    expect($prompt)->toContain('<known_facts>');
    expect($prompt)->toContain('</known_facts>');
    expect($prompt)->toContain('- name: "Test User"');
    expect($prompt)->toContain('- date_of_birth: "1985-04-14"');
    expect($prompt)->toContain('- marital_status: "married"');
    expect($prompt)->toContain('- employment_status: "employed"');
    expect($prompt)->toContain('- annual_employment_income: 50000');
    expect($prompt)->toContain('- monthly_expenditure: 2500');
    expect($prompt)->toContain('Do not ask the user for any field above.');
});

it('AdvicePromptBuilder::build injects <known_facts> with every authoritative field name', function () {
    $user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'User',
        'date_of_birth' => '1985-04-14',
        'marital_status' => 'married',
        'employment_status' => 'employed',
        'annual_employment_income' => 50000.00,
        'monthly_expenditure' => 2500.00,
    ]);

    $prompt = app(AdvicePromptBuilder::class)->build($user);

    expect($prompt)->toContain('<known_facts>');
    expect($prompt)->toContain('</known_facts>');
    expect($prompt)->toContain('- name: "Test User"');
    expect($prompt)->toContain('- date_of_birth: "1985-04-14"');
    expect($prompt)->toContain('- marital_status: "married"');
    expect($prompt)->toContain('- annual_employment_income: 50000');
    expect($prompt)->toContain('- monthly_expenditure: 2500');
    expect($prompt)->toContain('Do not ask the user for any field above.');
});

it('AdvicePromptBuilder::build still works without an active conversation (Layer 1 only)', function () {
    $user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'User',
        'marital_status' => 'married',
    ]);

    $prompt = app(AdvicePromptBuilder::class)->build($user);

    // Even with no conversation we still emit the block from Layer 1 facts
    expect($prompt)->toContain('<known_facts>');
    expect($prompt)->toContain('- marital_status: "married"');
});

it('asset_capture prompt — base_spouse-style scenario: marital_status known, must not be re-asked', function () {
    // Plan §S1.4 acceptance: seed marital_status='married'; assert no
    // prompt asks user's own marital status. Most direct expression of
    // INV-2.2.3 — the no-repeat-ask invariant.
    $user = User::factory()->create([
        'marital_status' => 'married',
    ]);

    $prompt = app(OnboardingPromptBuilder::class)->buildAssetCapturePrompt($user, 'protection');

    expect($prompt)->toContain('- marital_status: "married"');
    expect($prompt)->toContain('Do not ask the user for any field above.');
});
