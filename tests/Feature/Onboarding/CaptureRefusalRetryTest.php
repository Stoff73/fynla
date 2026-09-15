<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

// CSJ 2026-09-15: at the bank, pension and other capture steps the model
// sometimes answers a plain answer with its canned refusal and no tool call;
// the same sentence re-sent landed every time. One automatic re-run, then the
// retry copy — never a loop.

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

afterEach(function (): void {
    Mockery::close();
});

function refusalRetryUser(): User
{
    return User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['bank', 'savings']],
    ]);
}

it('re-runs a refused capture turn once and lands the record in the same message', function (): void {
    $user = refusalRetryUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    FynStreamHarness::fake()
        ->textTurn('I can only help with financial planning questions. How can I assist with your finances?')
        ->toolTurn('create_savings_account', [
            'institution' => 'Halifax',
            'account_name' => 'Halifax Savings Account',
            'account_type' => 'easy_access',
            'interest_rate' => 0,
            'current_balance' => 4567,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
        ], 'toolu_halifax')
        ->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, 'joint Halifax savings acc with 4567'
    ), false);

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and((float) $account->current_balance)->toBe(4567.0)
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))
        ->not->toContain("Sorry, I didn't catch that")
        ->not->toContain('I can only help');
});

it('re-runs only once — a second refusal falls through to the retry copy, no loop', function (): void {
    $user = refusalRetryUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    FynStreamHarness::fake()
        ->textTurn('I can only help with financial planning questions. How can I assist with your finances?')
        ->textTurn('I can only help with financial planning questions. How can I assist with your finances?')
        ->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, 'joint Halifax savings acc with 4567'
    ), false);

    // Two refusals: no third call. The deterministic savings backstop
    // (capture_focus on the bank step) reads the sentence itself and lands
    // the record, so the walk still moves on.
    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and((float) $account->current_balance)->toBe(4567.0)
        ->and($account->ownership_type)->toBe('joint')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))
        ->not->toContain('I can only help');
});

it('falls through to the retry copy after one re-run when nothing can be read from the sentence', function (): void {
    $user = refusalRetryUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    FynStreamHarness::fake()
        ->textTurn('I can only help with financial planning questions. How can I assist with your finances?')
        ->textTurn('I can only help with financial planning questions. How can I assist with your finances?')
        ->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, 'they are all with the same bank'
    ), false);

    expect(SavingsAccount::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))
        ->toContain("Sorry, I didn't catch that");
});
