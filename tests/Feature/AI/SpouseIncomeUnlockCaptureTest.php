<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Actions\ActionCardService;
use App\Services\GDPR\ConsentService;
use App\Services\Mobile\RecommendationRouting;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
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
    // Spending recorded: the money for a top-up is known (CSJ 2026-09-30).
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'marital_status' => 'married', 'expenditure_entry_mode' => 'simple', 'monthly_expenditure' => 500]);
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

it('saves how much of the spouse income is earnings, and the form offers it', function (): void {
    $body = $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", ['message' => RecommendationRouting::strategyUnlockPrompt('spouse_income_amount')])
        ->assertOk()->streamedContent();
    expect($body)->toContain('spouse_annual_earnings')->and($body)->toContain('Of that, earnings from work');

    $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", ['form' => [
            'name' => 'spouse_household',
            'answers' => ['_lead' => ['spouse_annual_income' => 9000, 'spouse_annual_earnings' => 3000]],
            'record' => ['type' => 'spouse_household', 'id' => $this->user->id],
        ]])->assertOk()->streamedContent();

    $row = TaxStrategyHouseholdInput::where('user_id', $this->user->id)->first();
    expect((float) $row->spouse_annual_income)->toBe(9000.0)
        ->and((float) $row->spouse_annual_earnings)->toBe(3000.0);
});

// The card no longer says a spouse with earnings "has no earnings".
it('explains the spouse top-up from their earnings from work', function (?float $earnings, string $why): void {
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
    $this->seed(ActionHowToSeeder::class);
    $this->user->update(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 45000,
        'employment_status' => 'employed', 'date_of_birth' => now()->subYears(40)->toDateString()]);
    TaxStrategyHouseholdInput::create(['user_id' => $this->user->id, 'spouse_annual_income' => 9000, 'spouse_annual_earnings' => $earnings]);

    $card = app(ActionCardService::class)->for($this->user->fresh(), 'tax_non_earner_spouse_pension');

    expect($card['why'][0])->toStartWith($why);
})->with([
    'earns £9,000' => [9000.0, 'Your spouse or civil partner earns £9,000 from work, so a pension payment for them gets basic-rate relief on up to £9,000 a year'],
    'no earnings' => [null, 'Without earnings from work, a pension payment for your spouse or civil partner still gets basic-rate relief on up to £3,600 a year'],
]);
