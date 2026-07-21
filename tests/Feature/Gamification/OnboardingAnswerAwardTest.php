<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 11 — onboarding/savetax answer awards.
 *
 * Every committed onboarding step funnels through
 * OnboardingChatDirector::recordProgress (interpret, skip, parking-hydrate,
 * grouped extract, asset capture, terminal, done). The gamification award
 * lives there, keyed onboarding:{stateId}, so each distinct step pays once
 * and re-answering the same step does not re-award.
 */
function invokeRecordProgress(User $user, string $stateId): void
{
    $director = app(OnboardingChatDirector::class);
    $reflection = new ReflectionMethod($director, 'recordProgress');
    $reflection->setAccessible(true);
    $reflection->invoke($director, $user, $stateId, ['value' => 'answer']);
}

it('awards onboarding points once per distinct answered step', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $perAnswer = (int) config('gamification.points.onboarding_answer');

    invokeRecordProgress($user, OnboardingStateMachine::STATE_BASE_PERSONAL);
    invokeRecordProgress($user, OnboardingStateMachine::STATE_BASE_EMPLOYMENT);

    expect(UserGamification::where('user_id', $user->id)->value('total_points'))
        ->toBe($perAnswer * 2);
    expect(PointAward::where('user_id', $user->id)->where('source_type', 'onboarding')->count())
        ->toBe(2);
});

it('does not re-award when the same step is answered again', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $perAnswer = (int) config('gamification.points.onboarding_answer');

    invokeRecordProgress($user, OnboardingStateMachine::STATE_BASE_PERSONAL);
    invokeRecordProgress($user, OnboardingStateMachine::STATE_BASE_PERSONAL);

    expect(UserGamification::where('user_id', $user->id)->value('total_points'))
        ->toBe($perAnswer);
    expect(PointAward::where('user_id', $user->id)
        ->where('dedup_key', 'onboarding:'.OnboardingStateMachine::STATE_BASE_PERSONAL)
        ->count())->toBe(1);
});

it('never awards onboarding points to preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    invokeRecordProgress($user, OnboardingStateMachine::STATE_BASE_PERSONAL);

    expect(UserGamification::where('user_id', $user->id)->exists())->toBeFalse();
    expect(PointAward::where('user_id', $user->id)->exists())->toBeFalse();
});
