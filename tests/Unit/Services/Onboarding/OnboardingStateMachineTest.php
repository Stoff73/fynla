<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('OnboardingStateMachine::states', function () {
    it('defines the full state list referenced by the spec', function () {
        $states = OnboardingStateMachine::states();

        $expected = [
            OnboardingStateMachine::STATE_PATH_CHOICE,
            OnboardingStateMachine::STATE_JOURNEY_SELECTION,
            OnboardingStateMachine::STATE_FOCUS_SELECTION,
            OnboardingStateMachine::STATE_BASE_PERSONAL,
            OnboardingStateMachine::STATE_BASE_SPOUSE,
            OnboardingStateMachine::STATE_BASE_DEPENDANTS,
            OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL,
            OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY,
            OnboardingStateMachine::STATE_BASE_EMPLOYMENT,
            OnboardingStateMachine::STATE_BASE_WORK,
            OnboardingStateMachine::STATE_BASE_EMPLOYMENT_MORE,
            OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE,
            OnboardingStateMachine::STATE_BASE_EXPENDITURE,
            OnboardingStateMachine::STATE_PROFILE_REVIEW_EXPENDITURE,
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
            expect($state['turn_type'])->toBeIn(['bubbles', 'free_text', 'delegated', 'terminal', 'grouped_extract'])
                ->and($id)->toBeString();
        }
    });

    it('every bubble state defines between 1 and 8 bubbles', function () {
        // Profile-review pauses have a single confirmation bubble. Focus
        // selection has 8 options (savings / investment / retirement /
        // protection / estate / goals / budgeting / business). Other
        // bubble states carry 2-5 options.
        foreach (OnboardingStateMachine::states() as $id => $state) {
            if ($state['turn_type'] !== 'bubbles') {
                continue;
            }
            $bubbles = $state['bubbles'] ?? [];
            expect(count($bubbles))->toBeGreaterThanOrEqual(1)
                ->and(count($bubbles))->toBeLessThanOrEqual(8);
            foreach ($bubbles as $bubble) {
                expect($bubble)->toHaveKeys(['id', 'label']);
            }
        }
    });

    it('every grouped_extract state declares an extraction_tool + retry_text', function () {
        foreach (OnboardingStateMachine::states() as $id => $state) {
            if ($state['turn_type'] !== 'grouped_extract') {
                continue;
            }
            expect($state)->toHaveKey('extraction_tool')
                ->and($state['extraction_tool'])->toBeString()
                ->and($state['extraction_tool'])->not->toBe('')
                ->and($state)->toHaveKey('retry_text');
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

    it('declares the standard layout on profile review states', function () {
        expect(OnboardingStateMachine::getState(OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY)['layout'])->toBe('standard')
            ->and(OnboardingStateMachine::getState(OnboardingStateMachine::STATE_PROFILE_REVIEW_EXPENDITURE)['layout'])->toBe('standard');
    });

    it('surfaces skip_link metadata on base_spouse', function () {
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
        expect($state)->toHaveKey('skip_link')
            ->and($state['skip_link']['color'])->toBe('raspberry');
    });

    it('removes the Other employment bubble and renames Employed to Full-time', function () {
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_EMPLOYMENT);
        $labels = array_column($state['bubbles'], 'label');
        expect($labels)->not->toContain('Other')
            ->and($labels)->not->toContain('Employed')
            ->and($labels)->toContain('Full-time');
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

describe('OnboardingStateMachine::nextFromPersonal (post base_personal branch)', function () {
    it('routes married users to base_spouse', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => 'married',
        ]);
        expect(OnboardingStateMachine::nextFromPersonal('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE);
    });

    it('routes civil partnership users to base_spouse', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => 'civil_partnership',
        ]);
        expect(OnboardingStateMachine::nextFromPersonal('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE);
    });

    it('routes single users straight to dependants', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => 'single',
        ]);
        expect(OnboardingStateMachine::nextFromPersonal('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
    });

    it('routes divorced users straight to dependants', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => 'divorced',
        ]);
        expect(OnboardingStateMachine::nextFromPersonal('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
    });

    it('routes widowed users straight to dependants', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => 'widowed',
        ]);
        expect(OnboardingStateMachine::nextFromPersonal('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
    });

    it('stays on base_personal when DOB is captured but marital is still null (FR-M10 partial)', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => null,
        ]);
        expect(OnboardingStateMachine::nextFromPersonal('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
    });

    it('stays on base_personal when marital is captured but DOB is still null (FR-M10 partial)', function () {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'marital_status' => 'married',
        ]);
        expect(OnboardingStateMachine::nextFromPersonal('', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
    });
});

describe('OnboardingStateMachine::nextFromDependants', function () {
    it('routes yes to dependants_detail', function () {
        expect(OnboardingStateMachine::nextFromDependants('Yes'))
            ->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL);
    });

    it('routes no to profile_review_family (new Phase 10 path)', function () {
        expect(OnboardingStateMachine::nextFromDependants('No'))
            ->toBe(OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY);
    });

});

describe('OnboardingStateMachine::nextFromEmployment', function () {
    it('routes employed users to base_work', function () {
        $user = User::factory()->create(['employment_status' => 'employed']);
        expect(OnboardingStateMachine::nextFromEmployment('Employed', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_WORK);
    });

    it('routes self_employed users to base_work', function () {
        $user = User::factory()->create(['employment_status' => 'self_employed']);
        expect(OnboardingStateMachine::nextFromEmployment('Self-employed', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_WORK);
    });

    it('routes part_time users to base_work', function () {
        $user = User::factory()->create(['employment_status' => 'part_time']);
        expect(OnboardingStateMachine::nextFromEmployment('Part-time', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_WORK);
    });

    it('routes retired users to retirement_date', function () {
        $user = User::factory()->create(['employment_status' => 'retired']);
        expect(OnboardingStateMachine::nextFromEmployment('Retired', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE);
    });

    it('routes unemployed users straight to expenditure', function () {
        $user = User::factory()->create(['employment_status' => 'unemployed']);
        expect(OnboardingStateMachine::nextFromEmployment('Unemployed', $user))
            ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
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

    it('skips base_personal when user already has DOB AND marital set', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => 'married',
        ]);
        $result = OnboardingStateMachine::applySkipRules(
            OnboardingStateMachine::STATE_BASE_PERSONAL,
            $user
        );
        expect($result)->not->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
    });

    it('does NOT skip base_personal when DOB is null', function () {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'marital_status' => 'married',
        ]);
        expect(OnboardingStateMachine::applySkipRules(
            OnboardingStateMachine::STATE_BASE_PERSONAL,
            $user
        ))->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
    });

    it('does NOT skip base_personal when marital is null', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-01-12',
            'marital_status' => null,
        ]);
        expect(OnboardingStateMachine::applySkipRules(
            OnboardingStateMachine::STATE_BASE_PERSONAL,
            $user
        ))->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
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
            OnboardingStateMachine::STATE_BASE_PERSONAL,
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
        expect($text)->toContain('Stocks & Shares ISAs');
    });

    it('uses "partner" in the base_spouse prompt for civil partnership', function () {
        $user = User::factory()->create(['marital_status' => 'civil_partnership']);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);
        expect($text)->toContain('partner')
            ->and($text)->not->toContain('spouse');
    });

    it('uses "spouse" in the base_spouse prompt for married', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);
        expect($text)->toContain('spouse');
    });

    it('uses trade-name phrasing in base_work for self-employed users', function () {
        $user = User::factory()->create(['employment_status' => 'self_employed']);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_WORK);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);
        expect($text)->toContain('trade or business name');
    });

    it('uses company phrasing in base_work for employed users', function () {
        $user = User::factory()->create(['employment_status' => 'employed']);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_WORK);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);
        expect($text)->toContain('company you work for');
    });
});

/**
 * FR-M10 (G2) — hybrid base_personal skip rule.
 *
 * When exactly one of {date_of_birth, marital_status} is set, the prompt
 * adapts to ask for only the missing field, pre-confirming the one already
 * captured. When both are set, skip_if handles the skip entirely; when
 * neither is set, the original grouped prompt is used.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M10
 */
describe('OnboardingStateMachine::buildPersonalPrompt (FR-M10)', function () {
    it('uses the original grouped prompt when neither DOB nor marital is set', function () {
        $user = User::factory()->create([
            'first_name' => 'Alex',
            'date_of_birth' => null,
            'marital_status' => null,
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        // Original prompt starts with "Let me grab a few basics first"
        expect($text)->toContain('grab a few basics')
            ->and($text)->toContain('Alex');
    });

    it('asks only for marital status when DOB is already set', function () {
        $user = User::factory()->create([
            'first_name' => 'Alex',
            'date_of_birth' => '1985-01-12',
            'marital_status' => null,
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        // Pre-confirms DOB ("I have you as born..."), asks for marital only.
        expect($text)->toContain('12 January 1985')
            ->and($text)->not->toContain('grab a few basics')
            ->and($text)->toContain('single');
    });

    it('asks only for DOB when marital status is already set', function () {
        $user = User::factory()->create([
            'first_name' => 'Alex',
            'date_of_birth' => null,
            'marital_status' => 'married',
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        // Pre-confirms marital, asks for DOB only. Should NOT enumerate
        // the full marital list again (i.e. no "single, married, civil
        // partnership, divorced, or widowed" phrasing).
        expect($text)->toContain('married')
            ->and($text)->toContain('date of birth')
            ->and($text)->not->toContain('grab a few basics')
            ->and($text)->not->toContain('divorced');
    });

    it('uses civil-partnership phrasing when that is the already-captured value', function () {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'marital_status' => 'civil_partnership',
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        expect($text)->toContain('civil partnership')
            ->and($text)->not->toContain('grab a few basics')
            ->and($text)->not->toContain('divorced');
    });
});

/**
 * No-repeat-ask contract for the spouse step. When the user volunteered
 * the spouse's first name upstream (parked into onboarding_parked_facts.spouse.first_name
 * by OnboardingFactExtractor at base_personal), the base_spouse prompt
 * must acknowledge the name and ask only for the remaining fields — DOB
 * and email. Re-asking is the visible regression CSJ caught (session 111).
 */
describe('OnboardingStateMachine::buildSpousePrompt — parked first_name awareness', function () {
    it('asks for first name + DOB + email when no spouse facts are parked', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        expect($text)->toContain('first name')
            ->and($text)->toContain('date of birth')
            ->and($text)->toContain('email');
    });

    it('omits the first-name ask when spouse.first_name is parked', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'onboarding_parked_facts' => [
                'spouse' => ['first_name' => 'Angela'],
            ],
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user, '', $conversation);

        expect($text)->toContain('Angela')
            ->and($text)->toContain('date of birth')
            ->and($text)->toContain('email')
            ->and($text)->not->toContain('first name');
    });

    it('falls back to the standard prompt when conversation is null', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user, '', null);

        expect($text)->toContain('first name');
    });

    it('falls back to the standard prompt when spouse bucket is empty', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'onboarding_parked_facts' => ['personal' => ['marital_status' => 'married']],
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE);
        $text = OnboardingStateMachine::resolvePromptText($state, $user, '', $conversation);

        expect($text)->toContain('first name');
    });
});
