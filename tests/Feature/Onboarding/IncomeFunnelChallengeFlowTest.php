<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Drain a director generator into a list of events. */
function drain(Generator $gen): array
{
    $events = [];
    foreach ($gen as $e) {
        $events[] = $e;
    }

    return $events;
}

it('challenges and holds base_work when the income contradicts the funnel band', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '100001_125140'],
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'annual_employment_income' => 50000,
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $m = new ReflectionMethod($director, 'maybeChallengeIncome');
    $m->setAccessible(true);
    $events = drain($m->invoke(
        $director, $user, $conversation,
        OnboardingStateMachine::STATE_BASE_WORK,
        ['annual_income' => 50000.0]
    ));

    $types = array_column($events, 'type');
    expect($types)->toContain('quick_replies');

    $qr = collect($events)->firstWhere('type', 'quick_replies');
    expect(collect($qr['bubbles'])->pluck('id')->all())->toBe(['continue', 'change']);

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_income_challenge']['band'])->toBe('100001_125140')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK); // held
});

it('does not challenge when the income is in-band', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '50271_100000'],
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'maybeChallengeIncome');
    $m->setAccessible(true);
    $events = drain($m->invoke(
        $director, $user, $conversation,
        OnboardingStateMachine::STATE_BASE_WORK,
        ['annual_income' => 80000.0]
    ));
    expect($events)->toBe([]);
    $user->refresh();
    expect($user->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull();
});

it('advances when the user taps Continue on the income challenge', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '100001_125140'],
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'annual_employment_income' => 50000,
        'onboarding_fyn_context' => ['pending_income_challenge' => ['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0]],
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    drain($director->handleUserMessage($user, $conversation, 'Continue'));
    $user->refresh();

    expect($user->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull()
        ->and($user->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_BASE_WORK); // advanced
});

it('re-asks the income question when the user taps Change', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '100001_125140'],
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'annual_employment_income' => 50000,
        'onboarding_fyn_context' => ['pending_income_challenge' => ['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0]],
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $events = drain($director->handleUserMessage($user, $conversation, 'Change'));
    $user->refresh();

    $content = collect($events)->where('type', 'content')->pluck('text')->implode(' ');
    expect($user->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull()
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK) // held for re-ask
        ->and(strtolower($content))->toContain('income');
});
