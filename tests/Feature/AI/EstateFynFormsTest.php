<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\DCPension;
use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Property;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Estate\EstateActionDefinitionService;
use App\Services\GDPR\ConsentService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * Item 9 (CSJ 2026-10-07): gifts and Lasting Powers of Attorney are recorded in
 * Fynla through Fyn's forms, opened from the estate how-tos; a pension's
 * beneficiary is on Fyn's pension form as it is on the web form — one form on
 * every surface (web, /m and, as typed questions, iOS).
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'marital_status' => 'single', 'date_of_birth' => '1960-01-01']);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
    FynStreamHarness::fake()->bind();
});

function estateAddContext(string $resource, string $screen = 'estate'): array
{
    return [
        'action' => 'add',
        'resource_type' => $resource,
        'resource_id' => null,
        'current_destination' => ['screen' => $screen, 'params' => [], 'fallback' => 'dashboard'],
        'origin' => ['kind' => 'surface_action', 'recommendation_id' => null],
    ];
}

function saveEstateForm($test, int $conversationId, array $form): void
{
    $test->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$conversationId}/messages", ['form' => $form])
        ->assertOk()->streamedContent();
}

it('opens Fyn on the gift form and records the gift', function (): void {
    $id = $this->postJson('/api/ai-chat/contextual-conversations', estateAddContext('gifts'))->assertCreated()->json('data.conversation.id');
    $opening = AiConversation::findOrFail($id)->messages()->first();
    expect($opening->metadata['capture_form']['name'])->toBe('gift');

    saveEstateForm($this, $id, ['name' => 'gift', 'answers' => ['pet' => [
        'recipient' => 'Sam', 'gift_date' => now()->subYear()->toDateString(), 'gift_value' => 20000,
    ]]]);

    $gift = Gift::where('user_id', $this->user->id)->first();
    expect($gift)->not->toBeNull()
        ->and($gift->gift_type)->toBe('pet')
        ->and((float) $gift->gift_value)->toBe(20000.0)
        ->and($gift->recipient)->toBe('Sam');
});

it('records a registered Lasting Power of Attorney with its date, and the card then names only the other kind', function (): void {
    $this->seed(EstateActionDefinitionSeeder::class);
    $id = $this->postJson('/api/ai-chat/contextual-conversations', estateAddContext('lpa'))->assertCreated()->json('data.conversation.id');
    expect(AiConversation::findOrFail($id)->messages()->first()->metadata['capture_form']['name'])->toBe('lpa');

    saveEstateForm($this, $id, ['name' => 'lpa', 'answers' => ['property_financial' => [
        'primary_attorney_name' => 'Alex', 'registered' => 'yes', 'registration_date' => '2024-03-01',
    ]]]);

    $lpa = LastingPowerOfAttorney::where('user_id', $this->user->id)->first();
    expect($lpa->status)->toBe('registered')
        ->and($lpa->is_registered_with_opg)->toBeTrue()
        ->and($lpa->registration_date->toDateString())->toBe('2024-03-01');

    $card = collect(app(EstateActionDefinitionService::class)->evaluateActions($this->user->fresh())['recommendations'])
        ->firstWhere('definition_key', 'no_lpa');
    expect($card['figures']['missing_financial'])->toBeFalse()
        ->and($card['figures']['missing_health'])->toBeTrue();
});

it('saves a pension beneficiary from Fyn\'s pension form, add and edit alike', function (): void {
    $this->seed(EstateActionDefinitionSeeder::class);
    $id = $this->postJson('/api/ai-chat/contextual-conversations', estateAddContext('retirement', 'retirement'))->assertCreated()->json('data.conversation.id');
    saveEstateForm($this, $id, ['name' => 'pension', 'answers' => ['personal' => [
        'provider' => 'Aviva', 'current_value' => 50000, 'beneficiary_name' => 'Sam',
    ]]]);
    $pension = DCPension::where('user_id', $this->user->id)->first();
    expect($pension->beneficiary_name)->toBe('Sam');

    $edit = $this->postJson('/api/ai-chat/contextual-conversations', [
        'action' => 'edit', 'resource_type' => 'dc_pension', 'resource_id' => $pension->id,
        'current_destination' => ['screen' => 'pension_detail', 'params' => ['pension_id' => $pension->id, 'pension_type' => 'dc'], 'fallback' => 'retirement'],
        'origin' => ['kind' => 'surface_action', 'recommendation_id' => null],
    ])->assertCreated()->json('data.conversation.id');
    $values = AiConversation::findOrFail($edit)->messages()->first()->metadata['capture_form_values'];
    expect($values['personal']['beneficiary_name'] ?? null)->toBe('Sam');

    saveEstateForm($this, $edit, ['name' => 'pension', 'answers' => ['personal' => [
        'provider' => 'Aviva', 'current_value' => 50000, 'beneficiary_name' => 'Jo',
    ]], 'record' => ['type' => 'dc_pension', 'id' => $pension->id]]);
    expect($pension->fresh()->beneficiary_name)->toBe('Jo');

    // The user's own line in the chat says whom it goes to, so they can see it was taken.
    $said = AiConversation::findOrFail($edit)->messages()->where('role', 'user')->latest('id')->value('content');
    expect($said)->toContain('to go to Jo if I die');
});

it('gives the Lasting Power of Attorney card a link that opens Fyn on its form', function (): void {
    foreach ([TaxActionDefinitionSeeder::class, SavingsActionDefinitionSeeder::class, ProtectionActionDefinitionSeeder::class,
        RetirementActionDefinitionSeeder::class, InvestmentActionDefinitionSeeder::class, EstateActionDefinitionSeeder::class, ActionHowToSeeder::class] as $seeder) {
        $this->seed($seeder);
    }
    Property::factory()->create(['user_id' => $this->user->id, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
        'current_value' => 300000, 'property_type' => 'main_residence']);

    $open = $this->getJson('/api/recommendations/actions')->assertOk()->json('data.open');
    $item = collect($open)->first(fn (array $i): bool => str_starts_with((string) $i['id'], 'estate_no_lpa'));
    expect($item)->not->toBeNull();

    $card = $this->getJson('/api/recommendations/actions/'.rawurlencode($item['id']))->assertOk()->json('data');
    $link = collect($card['learn_more'])->firstWhere('fyn');
    expect($link['label'])->toBe('Record a Lasting Power of Attorney with Fyn')
        ->and($link['fyn']['action'])->toBe('add')
        ->and($link['fyn']['resource_type'])->toBe('lpa')
        ->and($link['fyn']['origin']['kind'])->toBe('surface_action');

    // The request the link carries opens the form, as the clients post it.
    $this->postJson('/api/ai-chat/contextual-conversations', $link['fyn'])->assertCreated();
});
