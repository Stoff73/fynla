<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * Batch 5 (CSJ 2026-09-19). Laura (prod conversation 898) was asked for a
 * pension history, General Investment Account holdings and non-ISA savings
 * she did not have; "I have none of those" changed nothing and the asks
 * came back. The declaration is now recorded and answers every ask.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('records "I have none of those" against every locked strategy and stops asking', function (): void {
    TaxActionDefinition::create([
        'key' => 'pension_history_test', 'source' => 'strategy', 'strategy_type' => 'carry_forward',
        'title_template' => 'Carry forward', 'description_template' => 'Test', 'category' => 'Test', 'priority' => 'low',
        'scope' => 'portfolio', 'what_if_impact_type' => 'tax_optimisation', 'trigger_config' => [], 'is_enabled' => true,
        'sort_order' => 1, 'claim_tier' => 'mechanical', 'required_data' => ['pension_input_history', 'gia_holdings'],
    ]);
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'annual_employment_income' => 60000, 'employment_status' => 'employed', 'date_of_birth' => '1987-02-23']);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Chat']);
    Sanctum::actingAs($user);
    FynStreamHarness::fake()->bind(); // no model turn may run

    $before = app(HouseholdFinancialContext::class)->availability($user);
    expect($before['pension_input_history'])->toBeFalse()->and($before['gia_holdings'])->toBeFalse();

    $body = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'I have none of those'])
        ->assertOk()->streamedContent();

    expect($body)->toContain("I won't ask about")
        ->and($body)->toContain('pension contributions for the last three tax years')
        ->and($body)->toContain('General Investment Account holdings');

    $after = app(HouseholdFinancialContext::class)->availability($user->fresh());
    expect($after['pension_input_history'])->toBeTrue()
        ->and($after['gia_holdings'])->toBeTrue()
        ->and($user->fresh()->onboarding_fyn_context['declared_none_keys'])->toContain('gia_holdings');
});

it('leaves a plain "no" alone when nothing is locked', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Chat']);
    Sanctum::actingAs($user);
    $harness = FynStreamHarness::fake();
    $harness->textTurn('Understood.');
    $harness->bind();

    $body = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'No'])->assertOk()->streamedContent();

    expect($body)->not->toContain("I won't ask about");
});
