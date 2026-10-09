<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * Walk R38 (fynla.org, Casey, /m): "Add my Barclays easy access savings
 * account, £10,000 at 4%" with that account already on file opened a blank
 * savings form ("Fill this in and save"): the values were read onto the
 * Barclays record's form, where they changed nothing, and were lost.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('opens the add form filled in when the message restates a record already on file', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $account = SavingsAccount::factory()->create([
        'user_id' => $user->id, 'institution' => 'Barclays', 'account_type' => 'easy_access',
        'current_balance' => 10000, 'interest_rate' => 4, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Fyn']);

    config(['services.xai.api_key' => 'test']);
    $values = ['easy_access' => ['provider' => 'Barclays', 'current_value' => 10000, 'interest_rate' => 4]];
    Http::fakeSequence('api.x.ai/*')
        // First read: onto the record's form (index 0), where nothing changes.
        ->push(['choices' => [['message' => ['content' => json_encode(['forms' => ['0' => $values]])]]]])
        // Second read: the blank form alone.
        ->push(['choices' => [['message' => ['content' => json_encode(['forms' => ['0' => $values]])]]]]);

    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);
    $events = iterator_to_array($director->offerTypedForm(
        $user, $conversation, 'Add my Barclays easy access savings account, £10,000 at 4%',
        [['type' => 'savings_account', 'id' => $account->id, 'label' => 'Barclays easy access savings']],
        [CaptureForms::SAVINGS],
    ), false);

    $form = collect($events)->firstWhere('type', 'capture_form');
    expect($form)->not->toBeNull()
        ->and($form['prompt_text'] ?? null)->toBe(CaptureForms::FILLED_PROMPT)
        ->and($form['values']['easy_access']['provider'] ?? null)->toBe('Barclays')
        ->and($form['values']['easy_access']['current_value'] ?? null)->toBe(10000.0);
});
