<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the four fixes from April/April20Updates/fynChatAnalysis.md:
 *  §1 — add_more selection persistence
 *  §3 — handleCaptureWorkDetails partial capture
 *  §4 — ExpenditureProfile sync on base_expenditure capture
 *
 *  §2 (content event swallow) is verified via browser testing — it sits
 *  inside a generator loop that consumes delegated CoordinatingAgent output
 *  and isn't unit-testable without a full LLM mock.
 */
function invokePersistCapture(User $user, string $stateId, mixed $capturedValue): void
{
    $director = app(OnboardingChatDirector::class);
    $reflection = new ReflectionMethod($director, 'persistCapture');
    $reflection->setAccessible(true);

    $state = OnboardingStateMachine::getState($stateId);
    $reflection->invoke($director, $user, $state, [
        'ok' => true,
        'captured_value' => $capturedValue,
        'answer_for_transition' => (string) $capturedValue,
    ]);
}

function invokeCaptureWorkDetails(User $user, array $input): array
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'handleCaptureWorkDetails');
    $reflection->setAccessible(true);

    return $reflection->invoke($agent, $input, $user);
}

describe('Fix §1: add_more persists the new selection', function () {
    it('writes onboarding_fyn_selection when user picks a focus on add_more', function () {
        $user = User::factory()->create([
            'onboarding_fyn_selection' => 'protection',
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_ADD_MORE,
            'onboarding_fyn_context' => ['visited_focuses' => ['protection']],
        ]);

        invokePersistCapture($user, OnboardingStateMachine::STATE_ADD_MORE, 'savings');

        $user->refresh();
        expect($user->onboarding_fyn_selection)->toBe('savings')
            ->and($user->onboarding_fyn_context['visited_focuses'])
            ->toContain('protection')->toContain('savings');
    });

    it('does not overwrite selection when user picks "done"', function () {
        $user = User::factory()->create([
            'onboarding_fyn_selection' => 'savings',
            'onboarding_fyn_context' => ['visited_focuses' => ['protection', 'savings']],
        ]);

        invokePersistCapture($user, OnboardingStateMachine::STATE_ADD_MORE, 'done');

        $user->refresh();
        expect($user->onboarding_fyn_selection)->toBe('savings')
            ->and($user->onboarding_fyn_context['visited_focuses'])
            ->not->toContain('done');
    });

    it('does not duplicate an already-visited focus', function () {
        $user = User::factory()->create([
            'onboarding_fyn_selection' => 'protection',
            'onboarding_fyn_context' => ['visited_focuses' => ['protection', 'savings']],
        ]);

        invokePersistCapture($user, OnboardingStateMachine::STATE_ADD_MORE, 'savings');

        $user->refresh();
        $visited = $user->onboarding_fyn_context['visited_focuses'];
        expect($visited)->toBe(['protection', 'savings']); // no dupes appended
    });
});

describe('Fix §3: handleCaptureWorkDetails supports partial capture', function () {
    it('saves all three fields when present and reports no missing', function () {
        $user = User::factory()->create([
            'employment_status' => 'employed',
            'employer' => null,
            'occupation' => null,
            'annual_employment_income' => null,
        ]);

        $result = invokeCaptureWorkDetails($user, [
            'employer' => 'Dentsu',
            'occupation' => 'Chief Marketing Officer',
            'annual_income' => 50000,
        ]);

        $user->refresh();
        expect($result['onboarding_capture'])->toBeTrue()
            ->and($result['details']['missing'])->toBe([])
            ->and($user->employer)->toBe('Dentsu')
            ->and($user->occupation)->toBe('Chief Marketing Officer')
            ->and((float) $user->annual_employment_income)->toBe(50000.0);
    });

    it('saves non-empty fields and reports missing when employer is blank', function () {
        $user = User::factory()->create([
            'employment_status' => 'employed',
            'employer' => null,
            'occupation' => null,
            'annual_employment_income' => null,
        ]);

        $result = invokeCaptureWorkDetails($user, [
            'employer' => '',
            'occupation' => 'Chief Marketing Officer',
            'annual_income' => 50000,
        ]);

        $user->refresh();
        expect($result['onboarding_capture'])->toBeTrue()
            ->and($result['details']['missing'])->toBe(['employer'])
            ->and($user->employer ?? '')->toBe('')
            ->and($user->occupation)->toBe('Chief Marketing Officer')
            ->and((float) $user->annual_employment_income)->toBe(50000.0);
    });

    it('accumulates across turns without overwriting previously-saved fields', function () {
        $user = User::factory()->create([
            'employment_status' => 'employed',
            'employer' => 'Dentsu',
            'occupation' => null,
            'annual_employment_income' => null,
        ]);

        // Second turn — LLM only sees "CMO, £50k" and doesn't re-emit employer
        $result = invokeCaptureWorkDetails($user, [
            'employer' => '',
            'occupation' => 'Chief Marketing Officer',
            'annual_income' => 50000,
        ]);

        $user->refresh();
        expect($result['details']['missing'])->toBe([])
            ->and($user->employer)->toBe('Dentsu')
            ->and($user->occupation)->toBe('Chief Marketing Officer')
            ->and((float) $user->annual_employment_income)->toBe(50000.0);
    });

    it('routes income to self-employment field for self-employed users', function () {
        $user = User::factory()->create([
            'employment_status' => 'self_employed',
            'employer' => null,
            'occupation' => null,
            'annual_self_employment_income' => null,
        ]);

        $result = invokeCaptureWorkDetails($user, [
            'employer' => 'Acme Ltd',
            'occupation' => 'Consultant',
            'annual_income' => 80000,
        ]);

        $user->refresh();
        expect($result['details']['income_field'])->toBe('annual_self_employment_income')
            ->and((float) $user->annual_self_employment_income)->toBe(80000.0);
    });

    it('rejects income above the permitted range', function () {
        $user = User::factory()->create([
            'employment_status' => 'employed',
        ]);

        $result = invokeCaptureWorkDetails($user, [
            'employer' => 'Acme',
            'occupation' => 'CEO',
            'annual_income' => 100_000_000,
        ]);

        expect($result['error'] ?? false)->toBeTrue();
    });
});

describe('Fix §4: base_expenditure syncs into ExpenditureProfile', function () {
    it('creates an ExpenditureProfile row with total_monthly_expenditure on capture', function () {
        $user = User::factory()->create([
            'monthly_expenditure' => 0,
        ]);

        invokePersistCapture($user, OnboardingStateMachine::STATE_BASE_EXPENDITURE, 4000);

        $user->refresh();
        expect((float) $user->monthly_expenditure)->toBe(4000.0);

        $profile = ExpenditureProfile::where('user_id', $user->id)->first();
        expect($profile)->not->toBeNull()
            ->and((float) $profile->total_monthly_expenditure)->toBe(4000.0);
    });

    it('updates the existing ExpenditureProfile when the user already has one', function () {
        $user = User::factory()->create();
        ExpenditureProfile::create([
            'user_id' => $user->id,
            'total_monthly_expenditure' => 1000,
            'monthly_housing' => 500,
        ]);

        invokePersistCapture($user, OnboardingStateMachine::STATE_BASE_EXPENDITURE, 4000);

        $profile = ExpenditureProfile::where('user_id', $user->id)->first();
        expect($profile)->not->toBeNull()
            ->and((float) $profile->total_monthly_expenditure)->toBe(4000.0)
            ->and((float) $profile->monthly_housing)->toBe(500.0); // preserved
    });

    it('does not create a profile when the captured value is zero', function () {
        $user = User::factory()->create();

        invokePersistCapture($user, OnboardingStateMachine::STATE_BASE_EXPENDITURE, 0);

        expect(ExpenditureProfile::where('user_id', $user->id)->exists())->toBeFalse();
    });
});
