<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\StatePension;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * TODO item 6: the Retirement page says a recorded State Pension is "not
 * recorded as being paid", and /m's Update opens Fyn on it. Walking /m, Fyn
 * answered "I'm already being paid my State Pension" with "I'll update your
 * record" and wrote nothing (no write verb, so no capture). The edit now opens
 * on the State Pension's own form, whose save goes through the one handler.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'date_of_birth' => '1958-03-10']);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    FynStreamHarness::fake()->bind();
    $this->statePension = StatePension::create(['user_id' => $this->user->id, 'state_pension_forecast_annual' => 11502.4, 'ni_years_completed' => 35]);
});

function statePensionContext(int $id): array
{
    return [
        'action' => 'edit',
        'resource_type' => 'state_pension',
        'resource_id' => $id,
        'current_destination' => ['screen' => 'pension_detail', 'params' => ['pension_id' => $id, 'pension_type' => 'state'], 'fallback' => 'retirement'],
        'origin' => ['kind' => 'surface_action', 'recommendation_id' => null],
    ];
}

it('opens an edit of the State Pension on its form, asking whether it is paid', function (): void {
    $id = $this->postJson('/api/ai-chat/contextual-conversations', statePensionContext($this->statePension->id))
        ->assertCreated()->json('data.conversation.id');
    $opening = AiConversation::findOrFail($id)->messages()->first();

    expect($opening->metadata['capture_form']['name'])->toBe('state_pension')
        ->and(array_keys($opening->metadata['capture_form']['fields']))->toContain('already_receiving')
        ->and($opening->metadata['capture_form_values']['_lead']['already_receiving'] ?? null)->toBe('no')
        ->and((float) $opening->metadata['capture_form_values']['_lead']['forecast_annual'])->toBe(11502.4);
});

it('saves "being paid" through the edit path, so it counts as income', function (): void {
    $user = $this->user;
    $id = $this->postJson('/api/ai-chat/contextual-conversations', statePensionContext($this->statePension->id))->json('data.conversation.id');

    $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$id}/messages", ['form' => [
            'name' => 'state_pension',
            'answers' => ['_lead' => ['already_receiving' => 'yes', 'forecast_annual' => 11502.4, 'ni_years_completed' => 35]],
            'record' => ['type' => 'state_pension', 'id' => $user->id],
        ]])->assertOk()->streamedContent();

    expect(StatePension::where('user_id', $user->id)->first()->already_receiving)->toBeTrue();
});

it('records "not yet" as not being paid', function (): void {
    $this->statePension->update(['already_receiving' => true]);
    $id = $this->postJson('/api/ai-chat/contextual-conversations', statePensionContext($this->statePension->id))->json('data.conversation.id');

    $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$id}/messages", ['form' => [
            'name' => 'state_pension',
            'answers' => ['_lead' => ['already_receiving' => 'no']],
            'record' => ['type' => 'state_pension', 'id' => $this->user->id],
        ]])->assertOk()->streamedContent();

    expect(StatePension::where('user_id', $this->user->id)->first()->already_receiving)->toBeFalse();
});
