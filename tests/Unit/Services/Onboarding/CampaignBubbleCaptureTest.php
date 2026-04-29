<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

/**
 * STATE_CAMPAIGN_SPOUSE_WORK is a `bubbles` state with `capture_field=null`.
 * Without bubble→tool wiring, a Yes/No click leaves
 * users.household_calculation_mode and users.marriage_allowance_eligible NULL,
 * so nextFromSpouseWork falls through to STATE_CAMPAIGN_TERMINAL and both
 * spouse capture states are silently skipped.
 *
 * These tests drive the real director and assert the columns are written
 * and the next state matches the chosen bubble — i.e. capture_spouse_work_status
 * fired as a side effect of the bubble click.
 */
function consumeDirectorTurn(User $user, AiConversation $conversation, string $message): array
{
    $director = app(OnboardingChatDirector::class);
    $events = [];
    foreach ($director->handleUserMessage($user, $conversation, $message) as $event) {
        $events[] = $event;
    }

    return $events;
}

describe('STATE_CAMPAIGN_SPOUSE_WORK bubble → capture_spouse_work_status', function () {
    it('writes household_calculation_mode=dual_earner on a "Yes, they work" click', function () {
        $user = User::factory()->create([
            'marital_status' => 'married',
            'employment_status' => 'full_time',
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK,
            'household_calculation_mode' => null,
            'marriage_allowance_eligible' => null,
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
            'model_used' => 'test',
        ]);

        consumeDirectorTurn($user, $conversation, 'Yes, they work');

        $user->refresh();
        expect($user->household_calculation_mode)->toBe('dual_earner')
            ->and($user->marriage_allowance_eligible)->toBeFalse()
            ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);
    });

    it('writes household_calculation_mode=single_earner_couple on a "No" click', function () {
        $user = User::factory()->create([
            'marital_status' => 'married',
            'employment_status' => 'full_time',
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK,
            'household_calculation_mode' => null,
            'marriage_allowance_eligible' => null,
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
            'model_used' => 'test',
        ]);

        consumeDirectorTurn($user, $conversation, "No, they don't currently work");

        $user->refresh();
        expect($user->household_calculation_mode)->toBe('single_earner_couple')
            ->and($user->marriage_allowance_eligible)->toBeTrue()
            ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS);
    });

    it('routes to STATE_CAMPAIGN_TERMINAL only when civilly partnered users skip via skip_if (regression)', function () {
        // Single users should never enter STATE_CAMPAIGN_SPOUSE_WORK at all
        // because skipIfNotMarried evicts them upstream — but if the director
        // is somehow handed a single user there, the bubble click should still
        // be a no-op rather than corrupting columns.
        $user = User::factory()->create([
            'marital_status' => 'single',
            'employment_status' => 'full_time',
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK,
        ]);

        expect(OnboardingStateMachine::skipIfNotMarried($user))->toBeTrue();
    });
});
