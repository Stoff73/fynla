<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('OnboardingStateMachine::states', function () {
    it('defines all 16 states referenced by the spec', function () {
        $states = OnboardingStateMachine::states();

        $expected = [
            OnboardingStateMachine::STATE_PATH_CHOICE,
            OnboardingStateMachine::STATE_JOURNEY_SELECTION,
            OnboardingStateMachine::STATE_FOCUS_SELECTION,
            OnboardingStateMachine::STATE_BASE_DOB,
            OnboardingStateMachine::STATE_BASE_MARITAL,
            OnboardingStateMachine::STATE_BASE_SPOUSE,
            OnboardingStateMachine::STATE_BASE_DEPENDANTS,
            OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL,
            OnboardingStateMachine::STATE_BASE_EMPLOYMENT,
            OnboardingStateMachine::STATE_BASE_OCCUPATION,
            OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE,
            OnboardingStateMachine::STATE_BASE_INCOME,
            OnboardingStateMachine::STATE_BASE_EXPENDITURE,
            OnboardingStateMachine::STATE_ASSET_CAPTURE,
            OnboardingStateMachine::STATE_ADD_MORE,
            OnboardingStateMachine::STATE_DONE,
        ];

        foreach ($expected as $id) {
            expect($states)->toHaveKey($id);
        }

        expect(count($states))->toBe(count($expected));
    });

    it('only uses known turn_types', function () {
        foreach (OnboardingStateMachine::states() as $id => $state) {
            expect($state['turn_type'])->toBeIn(['bubbles', 'free_text', 'delegated', 'terminal'])
                ->and($id)->toBeString();
        }
    });

    it('every bubble state defines between 2 and 6 bubbles', function () {
        foreach (OnboardingStateMachine::states() as $id => $state) {
            if ($state['turn_type'] !== 'bubbles') {
                continue;
            }
            $bubbles = $state['bubbles'] ?? [];
            expect(count($bubbles))->toBeGreaterThanOrEqual(2)
                ->and(count($bubbles))->toBeLessThanOrEqual(6);
            foreach ($bubbles as $bubble) {
                expect($bubble)->toHaveKeys(['id', 'label']);
            }
        }
    });
});

describe('OnboardingStateMachine::getState', function () {
    it('returns state config for a known id', function () {
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_PATH_CHOICE);
        expect($state)->not->toBeNull()
            ->and($state['turn_type'])->toBe('bubbles')
            ->and(count($state['bubbles']))->toBe(2);
    });

    it('returns null for an unknown id', function () {
        expect(OnboardingStateMachine::getState('nonsense'))->toBeNull();
    });
});

describe('OnboardingStateMachine::nextFromPathChoice', function () {
    it('routes journey answer to journey_selection', function () {
        expect(OnboardingStateMachine::nextFromPathChoice('Follow a journey'))
            ->toBe(OnboardingStateMachine::STATE_JOURNEY_SELECTION);
    });

    it('routes focus answer to focus_selection', function () {
        expect(OnboardingStateMachine::nextFromPathChoice('Pick a focus'))
            ->toBe(OnboardingStateMachine::STATE_FOCUS_SELECTION);
    });

    it('defaults to focus_selection for anything else', function () {
        expect(OnboardingStateMachine::nextFromPathChoice('savings please'))
            ->toBe(OnboardingStateMachine::STATE_FOCUS_SELECTION);
    });
});

describe('OnboardingStateMachine::nextFromMarital', function () {
    it('routes married users to base_spouse', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        expect(OnboardingStateMachine::nextFromMarital('Married', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE);
    });

    it('routes civil partnership users to base_spouse', function () {
        $user = User::factory()->create(['marital_status' => 'civil_partnership']);
        expect(OnboardingStateMachine::nextFromMarital('Civil partnership', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE);
    });

    it('routes single users straight to dependants', function () {
        $user = User::factory()->create(['marital_status' => 'single']);
        expect(OnboardingStateMachine::nextFromMarital('Single', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
    });

    it('routes divorced users straight to dependants', function () {
        $user = User::factory()->create(['marital_status' => 'divorced']);
        expect(OnboardingStateMachine::nextFromMarital('Divorced', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
    });

    it('routes widowed users straight to dependants', function () {
        $user = User::factory()->create(['marital_status' => 'widowed']);
        expect(OnboardingStateMachine::nextFromMarital('Widowed', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
    });
});

describe('OnboardingStateMachine::nextFromDependants', function () {
    it('routes yes to dependants_detail', function () {
        expect(OnboardingStateMachine::nextFromDependants('Yes'))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL);
    });

    it('routes no to employment', function () {
        expect(OnboardingStateMachine::nextFromDependants('No'))
            ->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT);
    });
});

describe('OnboardingStateMachine::nextFromEmployment', function () {
    it('routes employed to occupation', function () {
        $user = User::factory()->create(['employment_status' => 'employed']);
        expect(OnboardingStateMachine::nextFromEmployment('Employed', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_OCCUPATION);
    });

    it('routes self_employed to occupation', function () {
        $user = User::factory()->create(['employment_status' => 'self_employed']);
        expect(OnboardingStateMachine::nextFromEmployment('Self-employed', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_OCCUPATION);
    });

    it('routes retired users to retirement_date', function () {
        $user = User::factory()->create(['employment_status' => 'retired']);
        expect(OnboardingStateMachine::nextFromEmployment('Retired', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE);
    });

    it('routes unemployed users straight to income', function () {
        $user = User::factory()->create(['employment_status' => 'unemployed']);
        expect(OnboardingStateMachine::nextFromEmployment('Unemployed', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_INCOME);
    });
});

describe('OnboardingStateMachine::nextFromAddMore', function () {
    it('routes done to the terminal state', function () {
        $user = User::factory()->create();
        expect(OnboardingStateMachine::nextFromAddMore("I'm done", $user))
            ->toBe(OnboardingStateMachine::STATE_DONE);
    });

    it('routes a new selection back to asset_capture', function () {
        $user = User::factory()->create();
        expect(OnboardingStateMachine::nextFromAddMore('Investment', $user))
            ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
    });
});

describe('OnboardingStateMachine::getNextStateId (branching via state config)', function () {
    it('follows path_choice → focus_selection', function () {
        $user = User::factory()->create();
        expect(OnboardingStateMachine::getNextStateId(
            OnboardingStateMachine::STATE_PATH_CHOICE,
            'Pick a focus',
            $user
        ))->toBe(OnboardingStateMachine::STATE_FOCUS_SELECTION);
    });

    it('skips base_dob when user already has DOB set', function () {
        $user = User::factory()->create(['date_of_birth' => '1985-01-12']);
        // From focus_selection the direct next is base_dob, but the user
        // already has date_of_birth so skip_rules should NOT block us from
        // advancing into base_dob (skip_if is evaluated on the TARGET state).
        // applySkipRules is called with base_dob and should advance past it.
        $result = OnboardingStateMachine::applySkipRules(
            OnboardingStateMachine::STATE_BASE_DOB,
            $user
        );
        expect($result)->not->toBe(OnboardingStateMachine::STATE_BASE_DOB);
    });

    it('does NOT skip base_dob when DOB is null', function () {
        $user = User::factory()->create(['date_of_birth' => null]);
        expect(OnboardingStateMachine::applySkipRules(
            OnboardingStateMachine::STATE_BASE_DOB,
            $user
        ))->toBe(OnboardingStateMachine::STATE_BASE_DOB);
    });

    it('skips base_income when user already has employment income', function () {
        $user = User::factory()->create(['annual_employment_income' => 75000]);
        expect(OnboardingStateMachine::applySkipRules(
            OnboardingStateMachine::STATE_BASE_INCOME,
            $user
        ))->not->toBe(OnboardingStateMachine::STATE_BASE_INCOME);
    });
});

describe('OnboardingStateMachine::matchBubble', function () {
    it('matches an exact label', function () {
        expect(OnboardingStateMachine::matchBubble(
            OnboardingStateMachine::STATE_PATH_CHOICE,
            'Pick a focus'
        ))->toBe('focus');
    });

    it('matches case-insensitively', function () {
        expect(OnboardingStateMachine::matchBubble(
            OnboardingStateMachine::STATE_PATH_CHOICE,
            'pick a focus'
        ))->toBe('focus');
    });

    it('matches the id directly', function () {
        expect(OnboardingStateMachine::matchBubble(
            OnboardingStateMachine::STATE_PATH_CHOICE,
            'journey'
        ))->toBe('journey');
    });

    it('returns null when nothing matches', function () {
        expect(OnboardingStateMachine::matchBubble(
            OnboardingStateMachine::STATE_PATH_CHOICE,
            'banana'
        ))->toBeNull();
    });

    it('returns null for a non-bubble state', function () {
        expect(OnboardingStateMachine::matchBubble(
            OnboardingStateMachine::STATE_BASE_DOB,
            'anything'
        ))->toBeNull();
    });
});

describe('OnboardingStateMachine::interpolate + resolvePromptText', function () {
    it('substitutes first_name from the user name', function () {
        $user = User::factory()->create(['first_name' => 'Emma', 'surname' => 'Carter']);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_PATH_CHOICE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);
        expect($text)->toContain('Hi Emma,');
    });

    it('substitutes selection on the done state', function () {
        $user = User::factory()->create(['first_name' => 'Chris', 'surname' => 'Jones']);
        $user->onboarding_fyn_selection = 'savings';
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_DONE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);
        expect($text)->toContain('Chris')
            ->and($text)->toContain('savings');
    });

    it('invokes callable prompt_text for asset_capture', function () {
        $user = User::factory()->create();
        $user->onboarding_fyn_selection = 'investment';
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_ASSET_CAPTURE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);
        expect($text)->toContain('investment accounts');
    });
});
