<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Mobile\RecommendationRouting;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

/*
 * Release walk 2026-09-28. "Unlock spouse's income info" → "Add it now" sent
 * "Help me add my spouse's total income a year, including any pension or rent
 * (enter 0 if none)". The write-intent router matched "pension" and opened a
 * pension capture, which answered with the security refusal. The spouse's
 * income has a form (the spouse details edit form); the unlock opens it.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'marital_status' => 'married']);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    $this->conversation = AiConversation::create(['user_id' => $this->user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Fyn']);
    Sanctum::actingAs($this->user);
    FynStreamHarness::fake()->bind();
});

it('asks to update the spouse income, naming nothing else', function (string $key): void {
    expect(RecommendationRouting::strategyUnlockPrompt($key))->toBe("Update my spouse's income");
})->with(['spouse_income', 'spouse_income_amount']);

it('opens the spouse details form with the income field for a married user with no spouse row yet', function (): void {
    $body = $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", ['message' => RecommendationRouting::strategyUnlockPrompt('spouse_income_amount')])
        ->assertOk()->streamedContent();

    expect($body)->toContain('"type":"capture_form"')
        ->and($body)->toContain('spouse_annual_income')
        ->and($body)->not->toContain('only help with financial planning')
        // The form's label already starts "Your": no "your Your".
        ->and($body)->toContain("Here's your spouse's details")
        ->and($body)->not->toContain('your Your');
});

it('saves the spouse income from that form', function (): void {
    $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", ['form' => [
            'name' => 'spouse_household',
            'answers' => ['_lead' => ['spouse_annual_income' => 9000]],
            'record' => ['type' => 'spouse_household', 'id' => $this->user->id],
        ]])->assertOk()->streamedContent();

    expect((float) TaxStrategyHouseholdInput::where('user_id', $this->user->id)->value('spouse_annual_income'))->toBe(9000.0);
});
