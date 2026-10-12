<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\WhatIfScenario;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * T7 (walk fix plan B2.6; P5 #3971). "Run a what-if for me: if I paid an
 * extra £20,000 a year into my Aviva pot from now until 60 …" was handed to
 * capture as a pension, and a pension form opened. A what-if's designed route
 * is `create_what_if_scenario` through capture (S0.5.r, AdviceFyn::WRITE_TOOLS):
 * a pension handoff on the question is refused back to the model, and a
 * `what_if_scenario` handoff saves the scenario.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'date_of_birth' => '1984-03-01']);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    $this->conversation = AiConversation::create(['user_id' => $this->user->id, 'title' => 'Chat', 'status' => 'active', 'model_used' => '']);
});

const T7_P5 = "Run a what-if for me: if I paid an extra £20,000 a year into my pension from now until 60, what would the pot and my net worth be at 60 compared with today's projection?";

it('never opens a pension form for a what-if request', function (): void {
    FynStreamHarness::fake()
        ->textTurn('') // the CoALA planner's own call
        ->toolTurn('delegate_to_capture', ['reason' => 'User states a pension contribution.', 'entity_types' => ['dc_pension']], 'toolu_p')
        ->textTurn('At 60 your pot would be higher; here is how.')
        ->bind();

    $body = $this->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", ['message' => T7_P5])
        ->assertOk()->streamedContent();

    expect($body)->not->toContain('"type":"capture_form"')
        ->and($this->conversation->messages()->where('role', 'assistant')->latest('id')->value('persona'))->toBe('advice');
});

it('saves the scenario through create_what_if_scenario when the request is handed to the scenario capture', function (): void {
    FynStreamHarness::fake()
        ->textTurn('')
        ->toolTurn('delegate_to_capture', ['reason' => 'User wants a what-if scenario.', 'entity_types' => ['what_if_scenario']], 'toolu_w')
        ->toolTurn('create_what_if_scenario', [
            'name' => 'Extra £20,000 a year into my pension until 60',
            'scenario_type' => 'retirement',
            'parameters' => ['retirement_age' => 60, 'pension_contribution' => 20000],
        ], 'toolu_c')
        ->textTurn('Your what-if scenario is saved.')
        ->bind();

    $body = $this->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", ['message' => T7_P5])
        ->assertOk()->streamedContent();

    expect(WhatIfScenario::where('user_id', $this->user->id)->value('name'))->toBe('Extra £20,000 a year into my pension until 60')
        ->and($body)->not->toContain('"type":"capture_form"');
});
