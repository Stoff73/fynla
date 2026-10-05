<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\ValueObjects\CaptureContext;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * 7a step 1 (CSJ 2026-10-05, option A; 2026-10-01, all capture through forms):
 * a typed answer where a setup step's form is waiting, and a change typed in
 * chat without "change my …", open the form filled in; nothing is saved until
 * the user presses Save. Before this, both were written straight from the text.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
    FynStreamHarness::fake()->bind(); // no turns queued: any chat model call fails the test
    config(['services.xai.api_key' => 'test']);
});

/** @param  array<string, mixed>  $forms  form index => section => field => value */
function formFillReturns(array $forms): void
{
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['forms' => $forms])]]]])]);
}

function formsDirector(): OnboardingChatDirector
{
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    return $director;
}

function directorConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Fyn'])->fresh();
}

it('fills in the setup step form from a typed answer and saves nothing', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false, 'onboarding_completed' => false, 'first_name' => 'Chris',
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['bank', 'savings']],
    ]);
    $conversation = directorConversation($user);
    formFillReturns(['0' => ['easy_access' => ['provider' => 'Nationwide', 'current_value' => 5000, 'interest_rate' => 4.1]]]);

    $events = iterator_to_array(formsDirector()->handleUserMessage($user, $conversation, 'I have a Nationwide easy access account with £5,000 at 4.1%'), false);
    $form = collect($events)->firstWhere('type', 'capture_form');
    $last = $conversation->messages()->latest('id')->first();

    expect($form['values'])->toBe(['easy_access' => ['provider' => 'Nationwide', 'current_value' => 5000.0, 'interest_rate' => 4.1]])
        ->and($form['prompt_text'])->toBe("I've filled in what you told me — check it, add anything missing and save.")
        ->and($last->metadata['onboarding_step'])->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS)
        ->and($last->metadata['capture_form_values']['easy_access']['provider'])->toBe('Nationwide')
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);
});

it('opens the one record a change typed in chat is about, filled in, and saves nothing', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Halifax', 'account_type' => 'easy_access', 'current_balance' => 4000, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    $monzo = SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Monzo', 'account_type' => 'easy_access', 'current_balance' => 900, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    $conversation = directorConversation($user);
    formFillReturns(['1' => ['easy_access' => ['current_value' => 1200]]]);

    $events = iterator_to_array(formsDirector()->handleInlineCapture(
        $user, $conversation, 'My Monzo balance is £1,200 now',
        CaptureContext::fromArray(['reason' => 'balance changed', 'entity_types' => ['savings_account'], 'fields_needed' => []]),
    ), false);
    $form = collect($events)->firstWhere('type', 'capture_form');

    expect($form['record'])->toEqual(['type' => 'savings_account', 'id' => $monzo->id])
        ->and($form['values']['easy_access']['current_value'])->toBe(1200.0)
        ->and((float) $monzo->fresh()->current_balance)->toBe(900.0);
});

it('asks which record when the change fits several, then fills in the one chosen', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    $halifax = SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Halifax', 'account_type' => 'easy_access', 'current_balance' => 4000, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    $monzo = SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Monzo', 'account_type' => 'easy_access', 'current_balance' => 900, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Barclays', 'account_type' => 'easy_access', 'current_balance' => 50, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    $conversation = directorConversation($user);
    formFillReturns(['0' => ['easy_access' => ['interest_rate' => 3.5]], '1' => ['easy_access' => ['interest_rate' => 3.5]]]);

    $events = iterator_to_array(formsDirector()->handleInlineCapture(
        $user, $conversation, 'My easy access account pays 3.5% now',
        CaptureContext::fromArray(['reason' => 'rate changed', 'entity_types' => ['savings_account'], 'fields_needed' => []]),
    ), false);
    $chooser = collect($events)->firstWhere('type', 'quick_replies');

    expect(array_column($chooser['bubbles'], 'id'))->toBe(["edit:savings_account:{$halifax->id}", "edit:savings_account:{$monzo->id}"]);

    formFillReturns(['0' => ['easy_access' => ['interest_rate' => 3.5]]]);
    $events = iterator_to_array(formsDirector()->handleAction($user->fresh(), $conversation, "edit:savings_account:{$monzo->id}"), false);
    $form = collect($events)->firstWhere('type', 'capture_form');

    expect($form['values']['easy_access']['interest_rate'])->toBe(3.5)
        ->and($form['prompt_text'])->toContain('with your change filled in');
});

it('leaves a new record to the capture turn', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Halifax', 'account_type' => 'easy_access', 'current_balance' => 4000, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    $conversation = directorConversation($user);
    formFillReturns([]);

    $events = iterator_to_array(formsDirector()->handleInlineCapture(
        $user, $conversation, 'I opened a Chase account with £2,000',
        CaptureContext::fromArray(['reason' => 'new account', 'entity_types' => ['savings_account'], 'fields_needed' => []]),
    ), false);

    expect(collect($events)->firstWhere('type', 'capture_form'))->toBeNull();
});
