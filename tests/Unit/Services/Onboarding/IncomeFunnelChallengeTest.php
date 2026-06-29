<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function invokeDetect(User $user, string $stateId, array $captureDetails)
{
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'detectIncomeFunnelMismatch');
    $m->setAccessible(true);

    return $m->invoke($director, $user, $stateId, $captureDetails);
}

it('flags a user income that falls outside the funnel band', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['income' => '100001_125140'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_WORK, [
        'details' => ['annual_income' => 50000.0],
    ]);
    expect($result)->toMatchArray(['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0]);
});

it('does not flag a user income inside the funnel band', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['income' => '50271_100000'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_WORK, [
        'details' => ['annual_income' => 80000.0],
    ]);
    expect($result)->toBeNull();
});

it('never flags when the user has no funnel answers', function () {
    $user = User::factory()->create(['funnel_answers' => null]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_WORK, [
        'details' => ['annual_income' => 50000.0],
    ]);
    expect($result)->toBeNull();
});

it('flags a spouse income that contradicts the funnel spouse band', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['spouseIncome' => 'zero'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_SPOUSE, [
        'details' => ['annual_income' => 40000.0],
    ]);
    expect($result)->toMatchArray(['field' => 'spouse', 'band' => 'zero', 'entered' => 40000.0]);
});

it('skips the spouse check when no spouse income was captured', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['spouseIncome' => 'zero'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_SPOUSE, [
        'details' => ['annual_income' => null],
    ]);
    expect($result)->toBeNull();
});

it('builds a challenge sentence naming the band and the entered figure', function () {
    $user = User::factory()->create();
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'buildIncomeChallenge');
    $m->setAccessible(true);
    $text = $m->invoke($director, ['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0], $user);
    expect($text)->toContain('£100,001–£125,140')
        ->and($text)->toContain('£50,000')
        ->and($text)->toContain('tax-saving');
});
