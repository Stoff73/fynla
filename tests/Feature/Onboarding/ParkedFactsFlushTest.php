<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INV-2.2.6 — once a state's bucket has been committed to its backing
 * row (users.*, family_members, expenditure_profiles), the corresponding
 * key in ai_conversations.onboarding_parked_facts must be removed so the
 * next <known_facts> block on subsequent prompts does not duplicate the
 * field. Each grouped_extract / free_text commit point is responsible
 * for clearing exactly its own bucket — siblings stay intact.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function makeUserAtState(string $stateId, ?string $selection = null): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => $stateId,
        'onboarding_fyn_selection' => $selection,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    return [$user, $conversation];
}

it('flushes the expenditure bucket after STATE_BASE_EXPENDITURE commit', function () {
    [$user, $conversation] = makeUserAtState(
        OnboardingStateMachine::STATE_BASE_EXPENDITURE
    );

    $conversation->update([
        'onboarding_parked_facts' => [
            'expenditure' => ['monthly_total_hint' => 2500.0],
            'spouse' => ['first_name' => 'Angela'],
        ],
    ]);

    $director = app(OnboardingChatDirector::class);
    foreach ($director->handleUserMessage($user, $conversation, '£2,500 a month') as $_) {
        // drain
    }

    $conversation->refresh();
    $parked = (array) ($conversation->onboarding_parked_facts ?? []);

    // The expenditure bucket has been written to expenditure_profiles
    // (and users.monthly_expenditure) — flush it.
    expect($parked)->not->toHaveKey('expenditure');

    // Sibling buckets (spouse) survive — they have not yet been
    // committed, so the <known_facts> layer still needs them.
    expect($parked)->toHaveKey('spouse');
    expect($parked['spouse']['first_name'])->toBe('Angela');
});

it('leaves parked facts untouched when the captured state has no flush mapping', function () {
    [$user, $conversation] = makeUserAtState(
        OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE
    );

    $conversation->update([
        'onboarding_parked_facts' => [
            'personal' => ['date_of_birth' => '1985-03-12'],
        ],
    ]);

    $director = app(OnboardingChatDirector::class);
    foreach ($director->handleUserMessage($user, $conversation, '2020') as $_) {
        // drain
    }

    $conversation->refresh();
    $parked = (array) ($conversation->onboarding_parked_facts ?? []);

    expect($parked)->toHaveKey('personal');
    expect($parked['personal']['date_of_birth'])->toBe('1985-03-12');
});

it('replaces the parked-facts column with null when the last surviving bucket is flushed', function () {
    // Direct invocation via reflection so the test focuses on the flush
    // contract itself, not on the OnboardingFactExtractor's incidental
    // parking of the user message that the integration paths trigger.
    [$user, $conversation] = makeUserAtState(
        OnboardingStateMachine::STATE_BASE_EXPENDITURE
    );

    $conversation->update([
        'onboarding_parked_facts' => [
            'expenditure' => ['monthly_total_hint' => 1800.0],
        ],
    ]);

    $director = app(OnboardingChatDirector::class);
    $reflection = new ReflectionClass($director);
    $method = $reflection->getMethod('flushParkedFactsForState');
    $method->setAccessible(true);
    $method->invoke($director, $conversation, OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $conversation->refresh();
    expect($conversation->onboarding_parked_facts)->toBeNull();
});

it('is a no-op when there are no parked facts to flush', function () {
    [$user, $conversation] = makeUserAtState(
        OnboardingStateMachine::STATE_BASE_EXPENDITURE
    );
    expect($conversation->onboarding_parked_facts)->toBeNull();

    $director = app(OnboardingChatDirector::class);
    $reflection = new ReflectionClass($director);
    $method = $reflection->getMethod('flushParkedFactsForState');
    $method->setAccessible(true);
    $method->invoke($director, $conversation, OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $conversation->refresh();
    expect($conversation->onboarding_parked_facts)->toBeNull();
});

it('flushes only the matching bucket when multiple buckets are parked', function () {
    [$user, $conversation] = makeUserAtState(
        OnboardingStateMachine::STATE_BASE_EXPENDITURE
    );

    $conversation->update([
        'onboarding_parked_facts' => [
            'personal' => ['date_of_birth' => '1985-03-12'],
            'spouse' => ['first_name' => 'Angela'],
            'employment' => ['status' => 'employed'],
            'expenditure' => ['monthly_total_hint' => 2500.0],
        ],
    ]);

    $director = app(OnboardingChatDirector::class);
    foreach ($director->handleUserMessage($user, $conversation, '£2,500 a month') as $_) {
        // drain
    }

    $conversation->refresh();
    $parked = (array) ($conversation->onboarding_parked_facts ?? []);

    expect($parked)->not->toHaveKey('expenditure');
    expect($parked)->toHaveKey('personal');
    expect($parked)->toHaveKey('spouse');
    expect($parked)->toHaveKey('employment');
});
