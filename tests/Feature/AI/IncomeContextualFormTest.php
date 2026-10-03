<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Tax\IncomeDefinitionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * TODO item 7a: /m Income → a source → "Edit details" opened Fyn with no form,
 * and the typed edit reached the dividend or other income figure only when the
 * message used certain words; trust income and a typed interest figure had no
 * Fyn path at all. The edit now opens on the form for that source (CSJ
 * 2026-10-01: all Fyn capture through forms).
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'annual_dividend_income' => 1200,
        'annual_interest_income' => null,
        'annual_trust_income' => null,
        'annual_other_income' => null,
    ]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    FynStreamHarness::fake()->bind();
});

function incomeSourceContext(string $source): array
{
    return [
        'action' => 'edit',
        'resource_type' => 'income',
        'current_destination' => ['screen' => 'income_detail', 'params' => ['income_owner' => 'user', 'income_source' => $source], 'fallback' => 'income'],
        'origin' => ['kind' => 'surface_action', 'recommendation_id' => null],
    ];
}

it('opens an edit of the dividends on the other-income form, filled in', function (): void {
    $id = $this->postJson('/api/ai-chat/contextual-conversations', incomeSourceContext('dividend'))
        ->assertCreated()->json('data.conversation.id');
    $opening = AiConversation::findOrFail($id)->messages()->first();

    expect($opening->content)->toStartWith('Here is your dividend, interest, trust and other income.')
        ->and($opening->metadata['capture_form']['name'])->toBe('other_income')
        ->and($opening->metadata['capture_form_record'])->toEqual(['type' => 'other_income', 'id' => $this->user->id])
        ->and((float) $opening->metadata['capture_form_values']['_lead']['annual_dividend_income'])->toBe(1200.0);
});

it('saves trust income and a typed interest figure through the form, and the Income page counts them', function (): void {
    $user = $this->user;
    $id = $this->postJson('/api/ai-chat/contextual-conversations', incomeSourceContext('dividend'))->json('data.conversation.id');

    $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$id}/messages", ['form' => [
            'name' => 'other_income',
            'answers' => ['_lead' => ['annual_dividend_income' => 1200, 'annual_interest_income' => 450, 'annual_trust_income' => 2000]],
            'record' => ['type' => 'other_income', 'id' => $user->id],
        ]])->assertOk()->streamedContent();

    $fresh = $user->fresh();
    expect((float) $fresh->annual_interest_income)->toBe(450.0)
        ->and((float) $fresh->annual_trust_income)->toBe(2000.0);

    $parts = app(IncomeDefinitionsService::class)->calculate($user->id)['components'];
    expect((float) $parts['interest'])->toBe(450.0)
        ->and((float) $parts['trust'])->toBe(2000.0);

    $reply = AiConversation::findOrFail($id)->messages()->where('role', 'assistant')->latest('id')->first()->content;
    expect($reply)->toContain('£2,000 a year in trust income');
});
