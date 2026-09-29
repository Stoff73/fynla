<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * "Record your employer benefits" (CSJ 2026-09-29): the card's Ask Fyn and the
 * /m Protection screen open a conversation on the employer benefits form, and
 * its save goes through the edit path to the one writer.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    FynStreamHarness::fake()->bind();
});

function employerBenefitsContext(): array
{
    return [
        'action' => 'edit',
        'resource_type' => 'employer_benefits',
        'resource_id' => null,
        'current_destination' => ['screen' => 'protection', 'params' => [], 'fallback' => 'dashboard'],
        'origin' => ['kind' => 'surface_action', 'recommendation_id' => null],
    ];
}

it('opens the conversation on the employer benefits form', function (): void {
    $user = $this->user;

    $id = $this->postJson('/api/ai-chat/contextual-conversations', employerBenefitsContext())
        ->assertCreated()->json('data.conversation.id');
    $opening = AiConversation::findOrFail($id)->messages()->first();

    expect($opening->content)->toStartWith('Here are your employer benefits.')
        ->and($opening->metadata['capture_form']['name'])->toBe('employer_benefits')
        ->and($opening->metadata['capture_form_record'])->toEqual(['type' => 'employer_benefits', 'id' => $user->id]);
});

it('saves the form through the edit path and closes the question', function (): void {
    $user = $this->user;
    $id = $this->postJson('/api/ai-chat/contextual-conversations', employerBenefitsContext())->json('data.conversation.id');

    $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$id}/messages", ['form' => [
            'name' => 'employer_benefits',
            'answers' => ['_lead' => ['provides' => 'yes', 'death_in_service_multiple' => 4, 'has_employer_pmi' => 'yes']],
            'record' => ['type' => 'employer_benefits', 'id' => $user->id],
        ]])->assertOk()->streamedContent();

    $profile = ProtectionProfile::where('user_id', $user->id)->first();
    expect((float) $profile->death_in_service_multiple)->toBe(4.0)
        ->and($profile->has_employer_pmi)->toBeTrue()
        ->and($profile->employer_benefits_recorded_at)->not->toBeNull();
});
