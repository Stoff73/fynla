<?php

declare(strict_types=1);

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * CSJ 2026-09-25: every onboarding goes through the Save Tax campaign. The
 * other entry routes stay in the code; onboarding.forced_campaign = null
 * restores them.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
    config()->set('onboarding.forced_campaign', 'savetax');
});

function forcedStart(User $user, array $body = [])
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return test()->withToken($user->createToken('t')->plainTextToken)
        ->postJson('/api/ai-chat/onboarding/start', $body);
}

function forcedFreshUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
        'funnel_answers' => null,
        'employment_status' => null,
        'marital_status' => null,
    ], $attrs));
}

it('sends a user with no from and no funnel answers into the Save Tax funnel questions', function () {
    $user = forcedFreshUser();
    forcedStart($user)->assertOk();
    $user->refresh();

    expect($user->onboarding_fyn_path)->toBe('campaign')
        ->and($user->onboarding_fyn_selection)->toBe('savetax')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT);
});

it('ignores a journey from value', function () {
    $user = forcedFreshUser();
    forcedStart($user, ['from' => 'retirement'])->assertOk();

    expect($user->refresh()->onboarding_fyn_selection)->toBe('savetax');
});

it('sends a pensioncheck funnel user into Save Tax at the first missing question', function () {
    $user = forcedFreshUser([
        'funnel_answers' => ['campaign' => 'pensioncheck', 'employment' => 'full-time'],
        'employment_status' => 'full_time',
    ]);
    forcedStart($user)->assertOk();
    $user->refresh();

    expect($user->onboarding_fyn_selection)->toBe('savetax')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE);
});

it('keeps a fully funnelled savetax user on the existing base_work entry', function () {
    $user = forcedFreshUser([
        'funnel_answers' => [
            'campaign' => 'savetax', 'employment' => 'full-time', 'income' => '50271_100000',
            'spouse' => 'no', 'assets' => ['bank'],
        ],
        'employment_status' => 'full_time',
    ]);
    forcedStart($user)->assertOk();

    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK);
});

it('refuses Pension Check re-entry for a completed user', function () {
    $user = forcedFreshUser(['onboarding_completed' => true]);

    forcedStart($user, ['from' => 'pensioncheck'])->assertStatus(409);
});

it('restores the old routing when forced_campaign is null', function () {
    config()->set('onboarding.forced_campaign', null);
    $user = forcedFreshUser();
    forcedStart($user)->assertOk();

    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});

it('tells every client which campaign is forced', function () {
    $user = forcedFreshUser();
    $data = (new UserResource($user))->toArray(request());
    expect($data['onboarding_forced_campaign'])->toBe('savetax');

    config()->set('onboarding.forced_campaign', null);
    $data = (new UserResource($user))->toArray(request());
    expect($data['onboarding_forced_campaign'])->toBeNull();
});

it('lets a completed user already mid-Pension-Check keep resuming', function () {
    $user = forcedFreshUser([
        'onboarding_completed' => true,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
    ]);
    $response = forcedStart($user)->assertOk();

    expect($response->streamedContent())->toContain('"type":"resume"')
        ->and($user->refresh()->onboarding_fyn_selection)->toBe('pensioncheck');
});
