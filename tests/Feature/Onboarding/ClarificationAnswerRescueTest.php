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

/**
 * Live prod conversation 843 (CSJ, 2026-09-11). Turn 1: the model called
 * create_savings_account without an owner and the gate asked "Is this in
 * your name only…". Turn 2: the user answered "My name"; the model replied
 * with its canned refusal and NO tool call, so nothing could repair the
 * owner and the walk fell to "Sorry, I didn't catch that". Every earlier
 * ownership fix assumed the model re-calls its tool; this pins the path
 * that does not need it.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

afterEach(function (): void {
    Mockery::close();
});

it('saves the previous gate-blocked account from a bare ownership reply even when the model makes no tool call', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['bank', 'savings']],
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ])->fresh();
    $director = app(OnboardingChatDirector::class);

    // Turn 1 — the model's call carries everything but the owner; the gate blocks it.
    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', [
            'institution' => 'Lloyds',
            'account_name' => 'Lloyds current account',
            'account_type' => 'current_account',
            'interest_rate' => 0.5,
            'current_balance' => 250,
        ], 'toolu_lloyds')
        ->textTurn('Is this in your name only, or do you share it with someone else?')
        ->bind();

    iterator_to_array($director->handleUserMessage($user, $conversation, 'Lloyds current account, 250 balance, 0.5% interest'), false);

    expect(SavingsAccount::where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);

    // Turn 2 — the exact live reply, and the model's exact live non-answer.
    FynStreamHarness::fake()
        ->textTurn('I can only help with financial planning questions. How can I assist with your finances?')
        ->bind();

    $events = iterator_to_array($director->handleUserMessage($user, $conversation, 'My name'), false);

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and($account->institution)->toBe('Lloyds')
        ->and((float) $account->current_balance)->toBe(250.0)
        ->and($account->ownership_type)->toBe('individual')
        ->and(collect($events)->pluck('type')->all())->toContain('entity_created')
        ->and(collect($events)->pluck('text')->filter()->implode(' '))->not->toContain("didn't catch that")
        ->and($user->fresh()->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);
});

it('does nothing when the previous turn was not a gate-blocked attempt', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['bank', 'savings']],
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ])->fresh();

    FynStreamHarness::fake()
        ->textTurn('I can only help with financial planning questions. How can I assist with your finances?')
        ->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'My name'), false);

    expect(SavingsAccount::where('user_id', $user->id)->exists())->toBeFalse();
});
