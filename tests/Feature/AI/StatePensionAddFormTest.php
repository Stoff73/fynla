<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\StatePension;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\CaptureForms;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * Regression walk 2026-10-09, R13: the Retirement page's "Add it" beside "No State
 * Pension forecast entered" opened Fyn's general pension form (workplace or
 * personal only), so the State Pension could not be added from it.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'marital_status' => 'single', 'date_of_birth' => '1984-05-14']);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    FynStreamHarness::fake()->bind();
});

it('opens the State Pension form for an add and records it', function (): void {
    $id = $this->postJson('/api/ai-chat/contextual-conversations', [
        'action' => 'add',
        'resource_type' => 'state_pension_forecast',
        'resource_id' => null,
        'current_destination' => ['screen' => 'retirement', 'params' => [], 'fallback' => 'dashboard'],
        'origin' => ['kind' => 'surface_action', 'recommendation_id' => null],
    ])->assertCreated()->json('data.conversation.id');

    $opening = AiConversation::findOrFail($id)->messages()->first();
    // An add: the add prompt and the form's own button, not "Change what needs changing" / "Save changes".
    expect($opening->metadata['capture_form']['name'])->toBe('state_pension')
        ->and($opening->content)->toBe(CaptureForms::ADD_PROMPT)
        ->and($opening->metadata['capture_form']['submit_label'])->toBe('Save');

    $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$id}/messages", ['form' => ['name' => 'state_pension', 'answers' => ['_lead' => [
            'already_receiving' => 'no', 'forecast_annual' => 12000, 'ni_years_completed' => 20,
        ]]]])
        ->assertOk()->streamedContent();

    $statePension = StatePension::where('user_id', $this->user->id)->first();
    expect($statePension)->not->toBeNull()
        ->and((float) $statePension->state_pension_forecast_annual)->toBe(12000.0)
        ->and((bool) $statePension->already_receiving)->toBeFalse();
});
