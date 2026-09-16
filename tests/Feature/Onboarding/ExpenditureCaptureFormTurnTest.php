<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CSJ 2026-09-16: expenditure as a form — one box for everyone, category
 * entry for Premium (the web page's five groups), both on the Save Tax walk
 * with no page visit after.
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

function expenditureUser(bool $premium): User
{
    $factory = $premium ? User::factory()->withActivePremiumSubscription() : User::factory();

    return $factory->create([
        'is_preview_user' => false, 'onboarding_completed' => false, 'first_name' => 'Chris', 'marital_status' => 'single',
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_EXPENDITURE,
        'monthly_expenditure' => null, 'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['bank']],
    ]);
}

function expenditureConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
}

function emitExpenditureForm(User $user, AiConversation $conversation): array
{
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);
    $emitted = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_BASE_EXPENDITURE, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_EXPENDITURE)), false);

    return collect($emitted)->firstWhere('type', 'capture_form');
}

it('a Free user gets the one-box form and its figure lands on the user, the profile and simple entry mode', function (): void {
    $user = expenditureUser(false);
    $conversation = expenditureConversation($user);
    $form = emitExpenditureForm($user, $conversation);
    expect($form['prompt_text'])->toBe('Now your spending.')
        ->and($form['form']['name'])->toBe('expenditure');

    FynStreamHarness::fake()->bind();
    $posted = ['name' => 'expenditure', 'answers' => ['_lead' => ['monthly_total' => 2400]]];
    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise($posted), null, true, $posted), false);

    $user->refresh();
    expect((float) $user->monthly_expenditure)->toBe(2400.0)
        ->and($user->expenditure_entry_mode)->toBe('simple')
        ->and((float) ExpenditureProfile::where('user_id', $user->id)->value('total_monthly_expenditure'))->toBe(2400.0)
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('monthly spending')
        ->and($user->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
});

it('a Premium user gets the category form and its figures land by category with the total', function (): void {
    $user = expenditureUser(true);
    $conversation = expenditureConversation($user);
    $form = emitExpenditureForm($user, $conversation);
    expect($form['form']['name'])->toBe('expenditure_detailed')
        ->and(array_column($form['form']['kinds'], 'key'))->toBe(['essential', 'communication', 'lifestyle', 'children', 'other']);

    FynStreamHarness::fake()->bind();
    $posted = ['name' => 'expenditure_detailed', 'answers' => ['essential' => ['rent' => 900, 'food_groceries' => 400], 'lifestyle' => ['pets' => 60]]];
    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise($posted), null, true, $posted), false);

    $user->refresh();
    expect((float) $user->rent)->toBe(900.0)
        ->and((float) $user->food_groceries)->toBe(400.0)
        ->and((float) $user->pets)->toBe(60.0)
        ->and((float) $user->monthly_expenditure)->toBe(1360.0)
        ->and($user->expenditure_entry_mode)->toBe('category')
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($user->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
});

it('the typed ballpark figure writes through the same path as the one-box form', function (): void {
    $user = expenditureUser(false);
    $conversation = expenditureConversation($user);
    FynStreamHarness::fake()->bind();
    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'About £1,900 a month', null, false), false);

    $user->refresh();
    expect((float) $user->monthly_expenditure)->toBe(1900.0)
        ->and($user->expenditure_entry_mode)->toBe('simple')
        ->and((float) ExpenditureProfile::where('user_id', $user->id)->value('total_monthly_expenditure'))->toBe(1900.0);
});
