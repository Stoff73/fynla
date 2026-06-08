<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('STATE_PROFILE_REVIEW_EXPENDITURE branching', function () {
    it('routes path=campaign users to STATE_CAMPAIGN_INTRO (consent gate)', function () {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
            'employment_status' => 'full_time',
        ]);

        $next = OnboardingStateMachine::nextFromExpenditureReview('looks correct', $user);

        expect($next)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_INTRO);
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

    it('advances to the next section (expenditure) when household_calculation_mode is unset', function () {
        // Previously fell through to TERMINAL; now the section-ordered campaign
        // flow carries on to the next section instead of dead-ending.
        $user = User::factory()->create([
            'household_calculation_mode' => null,
            'marital_status' => 'married',
            'monthly_expenditure' => 0,
        ]);

        expect(OnboardingStateMachine::nextFromSpouseWork('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
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

describe('STATE_CAMPAIGN_INTRO routing', function () {
    it('routes "okay" answers to STATE_CAMPAIGN_OCCUPATIONAL_SCHEME', function () {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
        ]);

        expect(OnboardingStateMachine::nextFromCampaignIntro('Okay', $user))
            ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
    });

    it('routes "nope" answers to STATE_DONE so onboarding completes', function () {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
        ]);

        expect(OnboardingStateMachine::nextFromCampaignIntro('Nope', $user))
            ->toBe(OnboardingStateMachine::STATE_DONE);
    });

    it('falls back to STATE_DONE for unrecognised answers', function () {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'campaign',
        ]);

        expect(OnboardingStateMachine::nextFromCampaignIntro('not a bubble', $user))
            ->toBe(OnboardingStateMachine::STATE_DONE);
    });
});

describe('STATE_CAMPAIGN_INTRO prompt builder', function () {
    it('thanks the user by first_name and omits spouse phrasing for single users', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'marital_status' => 'single',
        ]);

        $prompt = OnboardingStateMachine::buildCampaignIntroPrompt('', $user, null);

        expect($prompt)->toStartWith('Thanks Verify for that information.')
            ->and($prompt)->not->toContain('spouse')
            ->and($prompt)->toEndWith('is that okay?');
    });

    it('includes the linked spouse first name for married users', function () {
        $spouse = User::factory()->create(['first_name' => 'Angela']);
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);

        $prompt = OnboardingStateMachine::buildCampaignIntroPrompt('', $user, null);

        expect($prompt)->toContain("including Angela's where it makes sense");
    });

    it('falls back to parked spouse first name when spouse User is not linked', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'marital_status' => 'married',
            'spouse_id' => null,
        ]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'onboarding_parked_facts' => ['spouse' => ['first_name' => 'Maya']],
        ]);

        $prompt = OnboardingStateMachine::buildCampaignIntroPrompt('', $user, $conversation);

        expect($prompt)->toContain("including Maya's where it makes sense");
    });

    it('uses "your spouse" generic phrasing when no spouse name is known', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'marital_status' => 'civil_partnership',
            'spouse_id' => null,
        ]);

        $prompt = OnboardingStateMachine::buildCampaignIntroPrompt('', $user, null);

        expect($prompt)->toContain("including your spouse's where it makes sense");
    });
});

describe('all 10 campaign states are reachable from getState()', function () {
    it('returns a defined state config for each new constant', function () {
        $constants = [
            OnboardingStateMachine::STATE_CAMPAIGN_INTRO,
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
