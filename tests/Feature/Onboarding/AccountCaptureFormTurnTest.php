<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * The three Save Tax account steps as capture forms (CSJ 2026-09-16), the
 * property form's shape: a forms client gets the short lead-in and the
 * schema, a typed client the existing prompt; a form answer writes through
 * the kind's own tool with no model call and advances to the "another?"
 * question; "yes" re-opens the form, "no" continues where the step used to go.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

afterEach(function (): void {
    Mockery::close();
});

function accountStepUser(string $step): User
{
    return User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'marital_status' => 'married',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => $step,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['isa', 'bank', 'savings', 'investments']],
    ]);
}

function accountConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
}

function submitForm(User $user, AiConversation $conversation, array $form): array
{
    FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test

    return iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise($form), null, true, $form
    ), false);
}

dataset('account steps', [
    'isa' => [OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS, 'isa', 'Now your ISAs.', 'Cash, Stocks & Shares'],
    'bank' => [OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS, 'savings', 'Now your bank and savings accounts.', 'interest rate'],
    'investment' => [OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS, 'investment', 'Now your investments.', 'General Investment Accounts'],
]);

it('emits the form with its short lead-in to a forms client and the typed prompt to any other', function (string $step, string $formName, string $leadIn, string $typedFragment): void {
    $user = accountStepUser($step);
    $conversation = accountConversation($user);
    $state = OnboardingStateMachine::getState($step);

    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);
    $events = iterator_to_array($director->emitTurnForState($user, $conversation, $step, $state), false);
    $form = collect($events)->firstWhere('type', 'capture_form');
    expect($form)->not->toBeNull()
        ->and($form['form'])->toBe(CaptureForms::schema($formName))
        ->and($form['prompt_text'])->toBe($leadIn);

    $typed = app(OnboardingChatDirector::class);
    $typed->setClientSupportsForms(false);
    $events = iterator_to_array($typed->emitTurnForState($user, accountConversation($user), $step, $state), false);
    expect(collect($events)->firstWhere('type', 'capture_form'))->toBeNull()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain($typedFragment)->not->toContain($leadIn);
})->with('account steps');

it('saves a cash ISA as a savings row and a stocks and shares ISA as an investment row, then asks for another', function (): void {
    $user = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS);
    $conversation = accountConversation($user);
    $form = ['name' => 'isa', 'answers' => [
        'cash_isa' => ['provider' => 'Nationwide', 'current_value' => 12000, 'paid_in_this_year' => 4000, 'interest_rate' => 4.5],
        'stocks_shares_isa' => ['provider' => 'Vanguard', 'current_value' => 30000, 'paid_in_this_year' => 6000],
    ]];

    $events = submitForm($user, $conversation, $form);

    $cash = SavingsAccount::where('user_id', $user->id)->first();
    $ss = InvestmentAccount::where('user_id', $user->id)->first();
    expect($cash)->not->toBeNull()
        ->and($cash->account_type)->toBe('cash_isa')
        ->and($cash->is_isa)->toBeTrue()
        ->and($cash->institution)->toBe('Nationwide')
        ->and((float) $cash->current_balance)->toBe(12000.0)
        ->and((float) $cash->interest_rate)->toBe(4.5)
        ->and((float) $cash->isa_subscription_amount)->toBe(4000.0)
        ->and($cash->ownership_type)->toBe('individual')
        ->and($ss)->not->toBeNull()
        ->and($ss->account_type)->toBe('isa')
        ->and($ss->isa_type)->toBe('stocks_and_shares')
        ->and($ss->provider)->toBe('Vanguard')
        ->and((float) $ss->current_value)->toBe(30000.0)
        ->and((float) $ss->isa_subscription_current_year)->toBe(6000.0)
        ->and(collect($events)->where('type', 'tool_use')->pluck('tool')->unique()->values()->all())->toBe(['create_savings_account', 'create_investment_account'])
        ->and(collect($events)->where('type', 'entity_created')->pluck('entity_type')->all())->toBe(['savings_account', 'investment_account'])
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and(collect($events)->firstWhere('type', 'quick_replies'))->not->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_MORE);
});

it('saves a joint current account at 50/50 and a savings account with its rate, then asks for another', function (): void {
    $user = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);
    $conversation = accountConversation($user);
    $form = ['name' => 'savings', 'answers' => [
        'current_account' => ['provider' => 'Barclays', 'current_value' => 3200, 'ownership_type' => 'joint'],
        'easy_access' => ['provider' => 'Marcus', 'current_value' => 10000, 'interest_rate' => 4.1, 'ownership_type' => 'individual'],
    ]];

    $events = submitForm($user, $conversation, $form);

    $current = SavingsAccount::where('user_id', $user->id)->where('account_type', 'current_account')->first();
    $easy = SavingsAccount::where('user_id', $user->id)->where('account_type', 'easy_access')->first();
    expect($current)->not->toBeNull()
        ->and($current->account_name)->toBe('Barclays current account')
        ->and((float) $current->current_balance)->toBe(3200.0)
        ->and($current->ownership_type)->toBe('joint')
        ->and((float) $current->ownership_percentage)->toBe(50.0)
        ->and($easy)->not->toBeNull()
        ->and((float) $easy->interest_rate)->toBe(4.1)
        ->and($easy->ownership_type)->toBe('individual')
        ->and(collect($events)->where('type', 'entity_created'))->toHaveCount(2)
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS_MORE);
});

it('saves a general investment account and a joint other investment with its share, then asks for another', function (): void {
    $user = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS);
    $conversation = accountConversation($user);
    $form = ['name' => 'investment', 'answers' => [
        'gia' => ['provider' => 'Vanguard', 'current_value' => 45000, 'ownership_type' => 'individual'],
        'other' => ['provider' => 'Freetrade', 'current_value' => 5000, 'ownership_type' => 'joint', 'ownership_percentage' => 60],
    ]];

    $events = submitForm($user, $conversation, $form);

    $gia = InvestmentAccount::where('user_id', $user->id)->where('account_type', 'gia')->first();
    $other = InvestmentAccount::where('user_id', $user->id)->where('account_type', 'other')->first();
    expect($gia)->not->toBeNull()
        ->and($gia->provider)->toBe('Vanguard')
        ->and((float) $gia->current_value)->toBe(45000.0)
        ->and($gia->ownership_type)->toBe('individual')
        ->and($other)->not->toBeNull()
        ->and($other->ownership_type)->toBe('joint')
        ->and((float) $other->ownership_percentage)->toBe(60.0)
        ->and(collect($events)->where('type', 'entity_created'))->toHaveCount(2)
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS_MORE);
});

it('at the Free cap the loop question states the limit and offers only the next section', function (): void {
    // Free holds two savings accounts. Saving the second fills the cap, so
    // the loop question must not offer "add another" (CSJ 2026-09-16).
    $user = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);
    $conversation = accountConversation($user);
    $events = submitForm($user, $conversation, ['name' => 'savings', 'answers' => [
        'current_account' => ['provider' => 'Barclays', 'current_value' => 1000, 'ownership_type' => 'individual'],
        'easy_access' => ['provider' => 'Marcus', 'current_value' => 2000, 'interest_rate' => 4, 'ownership_type' => 'individual'],
    ]]);

    $quick = collect($events)->firstWhere('type', 'quick_replies');
    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(2)
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS_MORE)
        ->and($quick['prompt_text'])->toBe("You've reached the Free plan's limit of 2 bank and savings accounts, so I can't add another here. You can upgrade after onboarding to add more.")
        ->and(array_column($quick['bubbles'], 'label'))->toBe(['Continue to the next section']);

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user->fresh(), $conversation, 'Continue to the next section'), false);
    expect($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce')
        ->and($user->fresh()->onboarding_fyn_context['verify_section'] ?? null)->toBe('savings');
});

it('a form refused only by the Free cap moves on to the loop question instead of parking', function (): void {
    $user = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);
    $conversation = accountConversation($user);
    submitForm($user, $conversation, ['name' => 'savings', 'answers' => [
        'current_account' => ['provider' => 'Barclays', 'current_value' => 1000, 'ownership_type' => 'individual'],
        'easy_access' => ['provider' => 'Marcus', 'current_value' => 2000, 'interest_rate' => 4, 'ownership_type' => 'individual'],
    ]]);
    // A stale client (or "Yes, add another" before this fix) submits a third.
    $user->refresh()->forceFill(['onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS])->save();

    $events = submitForm($user->fresh(), $conversation, ['name' => 'savings', 'answers' => [
        'notice' => ['provider' => 'Shawbrook', 'current_value' => 3000, 'interest_rate' => 4.6, 'ownership_type' => 'individual'],
    ]]);

    $errors = collect($events)->firstWhere('type', 'capture_form_errors');
    $quick = collect($events)->firstWhere('type', 'quick_replies');
    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(2)
        ->and($errors['errors']['notice']['message'])->toContain("plan's limit")
        ->and(collect($events)->firstWhere('type', 'capture_complete'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS_MORE)
        ->and($quick['prompt_text'])->toContain("reached the Free plan's limit of 2 bank and savings accounts")
        ->and(array_column($quick['bubbles'], 'label'))->toBe(['Continue to the next section']);
});

it('below the cap the loop question still offers another', function (): void {
    $user = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS);
    $conversation = accountConversation($user);
    $events = submitForm($user, $conversation, ['name' => 'investment', 'answers' => [
        'gia' => ['provider' => 'Vanguard', 'current_value' => 45000, 'ownership_type' => 'individual'],
    ]]);

    $quick = collect($events)->firstWhere('type', 'quick_replies');
    expect($quick['prompt_text'])->toBe('Do you have another investment account to add?')
        ->and(array_column($quick['bubbles'], 'label'))->toBe(['Yes, add another', "No, that's everything"]);
});

it('re-opens the form on "Yes, add another" and continues on "No" exactly where each step used to go', function (): void {
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $isaUser = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_ISA_MORE);
    $isaConversation = accountConversation($isaUser);
    // The loop question is the last assistant row, as it is live.
    AiMessage::create(['conversation_id' => $isaConversation->id, 'role' => 'assistant', 'content' => 'Do you have another ISA to add?', 'metadata' => ['onboarding_step' => OnboardingStateMachine::STATE_CAMPAIGN_ISA_MORE]]);
    $events = iterator_to_array($director->handleUserMessage($isaUser, $isaConversation, 'Yes, add another'), false);
    $reopened = collect($events)->firstWhere('type', 'capture_form');
    expect($reopened['form']['name'])->toBe('isa')
        // CSJ 2026-09-16: no "Now your ISAs." lead-in when re-opening after Yes.
        ->and($reopened['prompt_text'])->toBe('')
        ->and($isaUser->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS);

    $isaDone = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_ISA_MORE);
    $isaDoneConversation = accountConversation($isaDone);
    AiMessage::create(['conversation_id' => $isaDoneConversation->id, 'role' => 'assistant', 'content' => 'Do you have another ISA to add?', 'metadata' => ['onboarding_step' => OnboardingStateMachine::STATE_CAMPAIGN_ISA_MORE]]);
    $events = iterator_to_array($director->handleUserMessage($isaDone, $isaDoneConversation, "No, that's everything"), false);
    $bankForm = collect($events)->firstWhere('type', 'capture_form');
    expect($isaDone->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS)
        ->and($bankForm['form']['name'])->toBe('savings')
        // A different step's loop question is a fresh entry: the lead-in stays.
        ->and($bankForm['prompt_text'])->toBe('Now your bank and savings accounts.');

    $bankDone = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS_MORE);
    iterator_to_array($director->handleUserMessage($bankDone, accountConversation($bankDone), "No, that's everything"), false);
    expect($bankDone->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce')
        ->and($bankDone->fresh()->onboarding_fyn_context['verify_section'] ?? null)->toBe('savings');

    $invDone = accountStepUser(OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS_MORE);
    iterator_to_array($director->handleUserMessage($invDone, accountConversation($invDone), "No, that's everything"), false);
    expect($invDone->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce')
        ->and($invDone->fresh()->onboarding_fyn_context['verify_section'] ?? null)->toBe('investments');
});
