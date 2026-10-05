<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * Walking release #1071/#1077 on fynla.org (/m Personal Information, account
 * 795): "Actually my date of birth is 15 March 1981" reached Advice Fyn, which
 * listed the new date as recorded; nothing was saved. CSJ 2026-10-05, option A:
 * a typed change fills in the record's form and is saved only when the user
 * presses Save.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create([
        'is_preview_user' => false, 'onboarding_completed' => true,
        'date_of_birth' => '1981-03-14', 'gender' => 'female', 'marital_status' => 'single',
    ]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    FynStreamHarness::fake()->bind();
    config(['services.xai.api_key' => 'test']);
});

function personalEditConversation(): int
{
    return test()->postJson('/api/ai-chat/contextual-conversations', [
        'action' => 'edit',
        'resource_type' => 'personal_information',
        'resource_id' => null,
        'current_destination' => ['screen' => 'personal_information', 'params' => [], 'fallback' => 'dashboard'],
        'origin' => ['kind' => 'surface_action', 'recommendation_id' => null],
    ])->assertCreated()->json('data.conversation.id');
}

it('answers a typed change with the form, the change filled in, and saves nothing', function (): void {
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['fields' => ['date_of_birth' => '1981-03-15']])]]]])]);
    $id = personalEditConversation();

    $stream = $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$id}/messages", ['message' => 'Actually my date of birth is 15 March 1981'])
        ->assertOk()->streamedContent();
    $last = AiConversation::findOrFail($id)->messages()->latest('id')->first();

    expect($stream)->toContain('"type":"capture_form"')
        ->and($last->content)->toBe("Here's your details with your change filled in — check it and save.")
        ->and($last->metadata['capture_form_values']['_lead']['date_of_birth'])->toBe('1981-03-15')
        ->and($last->metadata['capture_form_record'])->toEqual(['type' => 'personal', 'id' => $this->user->id])
        ->and($this->user->fresh()->date_of_birth->format('Y-m-d'))->toBe('1981-03-14')
        ->and(AiConversation::findOrFail($id)->messages()->where('role', 'user')->pluck('content')->all())->toBe(['Actually my date of birth is 15 March 1981']);
});

it('leaves a question to Fyn as before', function (): void {
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['fields' => []])]]]])]);
    $id = personalEditConversation();

    $stream = $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$id}/messages", ['message' => 'Why do you need my gender?'])
        ->assertOk()->streamedContent();

    expect($stream)->not->toContain('"type":"capture_form"');
});
