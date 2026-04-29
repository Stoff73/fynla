<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

describe('STATE_PROFILE_REVIEW_EXPENDITURE branching', function () {
    it('routes path=campaign users to STATE_CAMPAIGN_OCCUPATIONAL_SCHEME', function () {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
            'employment_status' => 'full_time',
        ]);

        $next = OnboardingStateMachine::nextFromExpenditureReview('looks correct', $user);

        expect($next)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
    });

    it('routes path=journey users to STATE_ASSET_CAPTURE (regression — unchanged behaviour)', function () {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'journey',
            'onboarding_fyn_selection' => 'protection',
        ]);

        $next = OnboardingStateMachine::nextFromExpenditureReview('looks correct', $user);

        expect($next)->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
    });

    it('routes path=focus users to STATE_ASSET_CAPTURE (regression — unchanged behaviour)', function () {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'focus',
            'onboarding_fyn_selection' => 'savings',
        ]);

        expect(OnboardingStateMachine::nextFromExpenditureReview('looks correct', $user))
            ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
    });
});

describe('skip_if helpers for campaign branch', function () {
    it('skipIfNotEmployed returns true for self-employed/retired/unemployed', function () {
        foreach (['self_employed', 'retired', 'unemployed'] as $status) {
            $user = User::factory()->create(['employment_status' => $status]);
            expect(OnboardingStateMachine::skipIfNotEmployed($user))->toBeTrue();
        }
    });

    it('skipIfNotEmployed returns false for full_time/part_time', function () {
        foreach (['full_time', 'part_time'] as $status) {
            $user = User::factory()->create(['employment_status' => $status]);
            expect(OnboardingStateMachine::skipIfNotEmployed($user))->toBeFalse();
        }
    });

    it('skipIfNotMarried returns true for single/divorced/widowed', function () {
        foreach (['single', 'divorced', 'widowed'] as $status) {
            $user = User::factory()->create(['marital_status' => $status]);
            expect(OnboardingStateMachine::skipIfNotMarried($user))->toBeTrue();
        }
    });

    it('skipIfNotMarried returns false for married/civil_partnership', function () {
        foreach (['married', 'civil_partnership'] as $status) {
            $user = User::factory()->create(['marital_status' => $status]);
            expect(OnboardingStateMachine::skipIfNotMarried($user))->toBeFalse();
        }
    });

    it('skipIfNotDualEarner returns true unless mode=dual_earner', function () {
        $u = User::factory()->create(['household_calculation_mode' => 'single_earner_couple']);
        expect(OnboardingStateMachine::skipIfNotDualEarner($u))->toBeTrue();

        $u2 = User::factory()->create(['household_calculation_mode' => 'dual_earner']);
        expect(OnboardingStateMachine::skipIfNotDualEarner($u2))->toBeFalse();
    });

    it('skipIfNotSingleEarnerCouple returns true unless mode=single_earner_couple', function () {
        $u = User::factory()->create(['household_calculation_mode' => 'dual_earner']);
        expect(OnboardingStateMachine::skipIfNotSingleEarnerCouple($u))->toBeTrue();

        $u2 = User::factory()->create(['household_calculation_mode' => 'single_earner_couple']);
        expect(OnboardingStateMachine::skipIfNotSingleEarnerCouple($u2))->toBeFalse();
    });
});

describe('STATE_CAMPAIGN_SPOUSE_WORK routing', function () {
    it('routes dual_earner users to STATE_CAMPAIGN_SPOUSE_HOUSEHOLD', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'dual_earner',
            'marital_status' => 'married',
        ]);

        expect(OnboardingStateMachine::nextFromSpouseWork('Yes, they work', $user))
            ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);
    });

    it('routes single_earner_couple users to STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'marital_status' => 'married',
        ]);

        expect(OnboardingStateMachine::nextFromSpouseWork("No, they don't currently work", $user))
            ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS);
    });

    it('falls back to TERMINAL when household_calculation_mode is unset', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => null,
            'marital_status' => 'married',
        ]);

        expect(OnboardingStateMachine::nextFromSpouseWork('', $user))
            ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL);
    });
});

describe('STATE_CAMPAIGN_TERMINAL', function () {
    it('declares turn_type=terminal, navigate_to=/tax-strategy, next=STATE_DONE', function () {
        $state = OnboardingStateMachine::states()[OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL];

        // turn_type=terminal mirrors STATE_DONE; OnboardingChatDirector reads
        // navigate_to and emits a `navigate` SSE event when this state is reached.
        expect($state['turn_type'])->toBe('terminal');
        expect($state['navigate_to'])->toBe('/tax-strategy');
        expect($state['next'])->toBe(OnboardingStateMachine::STATE_DONE);
    });
});

describe('all 9 campaign states are reachable from getState()', function () {
    it('returns a defined state config for each new constant', function () {
        $constants = [
            OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME,
            OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS,
            OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
            OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS,
            OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS,
            OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK,
            OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD,
            OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS,
            OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL,
        ];

        foreach ($constants as $stateId) {
            $state = OnboardingStateMachine::getState($stateId);
            expect($state)->not->toBeNull("State {$stateId} not registered in states()");
            expect($state)->toHaveKey('turn_type');
            expect($state)->toHaveKey('next');
        }
    });
});
