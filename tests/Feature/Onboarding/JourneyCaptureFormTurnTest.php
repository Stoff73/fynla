<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\FamilyMember;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * The journey-path capture steps as forms (CSJ 2026-09-16): the same shapes
 * as the Save Tax steps, so onboarding through Fyn is one process whatever
 * the entry point. A forms client gets the short lead-in and the schema; a
 * form answer writes through the state's own tool with no model call, is
 * repeated back, and advances exactly where the typed answer used to go.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

afterEach(function (): void {
    Mockery::close();
});

function journeyStepUser(string $step, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'date_of_birth' => null,
        'marital_status' => null,
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_step' => $step,
        'onboarding_fyn_selection' => 'savings',
    ], $attributes));
}

function journeyConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
}

function submitJourneyForm(User $user, AiConversation $conversation, array $form): array
{
    FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test

    return iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise($form), null, true, $form
    ), false);
}

it('emits the personal form with its lead-in to a forms client and the typed question to any other', function (bool $forms): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_PERSONAL);
    $conversation = journeyConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms($forms);

    $emitted = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_BASE_PERSONAL, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL)), false);
    $formEvent = collect($emitted)->firstWhere('type', 'capture_form');
    if ($forms) {
        expect($formEvent['prompt_text'])->toBe('Let me grab a few basics first, Chris.')
            ->and($formEvent['form']['name'])->toBe('personal')
            ->and($formEvent['form']['lead_fields'])->toBe(['date_of_birth', 'marital_status']);
    } else {
        expect($formEvent)->toBeNull()
            ->and(collect($emitted)->where('type', 'content')->pluck('text')->implode(' '))->toContain('date of birth');
    }
})->with(['forms client' => [true], 'typed client' => [false]]);

it('saves date of birth and marital status from the personal form, repeats them back and moves to the spouse step when married', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_PERSONAL);
    $conversation = journeyConversation($user);

    $events = submitJourneyForm($user, $conversation, ['name' => 'personal', 'answers' => ['_lead' => ['date_of_birth' => '1985-01-12', 'marital_status' => 'married']]]);

    $user->refresh();
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1985-01-12')
        ->and($user->marital_status)->toBe('married')
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain("I've noted you're born on 12 January 1985 and married.")
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE);
});

it('a single user goes from the personal form to the dependants question', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_PERSONAL);
    $conversation = journeyConversation($user);

    submitJourneyForm($user, $conversation, ['name' => 'personal', 'answers' => ['_lead' => ['date_of_birth' => '1990-06-30', 'marital_status' => 'single']]]);

    expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
});

it('a date of birth outside the age bounds is refused on the form and the step does not move', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_PERSONAL);
    $conversation = journeyConversation($user);

    $events = submitJourneyForm($user, $conversation, ['name' => 'personal', 'answers' => ['_lead' => ['date_of_birth' => now()->subYears(10)->format('Y-m-d'), 'marital_status' => 'single']]]);

    expect(collect($events)->firstWhere('type', 'capture_form_errors'))->not->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL)
        ->and($user->fresh()->date_of_birth)->toBeNull();
});

it('emits the spouse details form with its skip link, both in the stream and on the saved row', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_SPOUSE, ['date_of_birth' => '1985-01-12', 'marital_status' => 'married']);
    $conversation = journeyConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $emitted = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_BASE_SPOUSE, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_SPOUSE)), false);
    $formEvent = collect($emitted)->firstWhere('type', 'capture_form');
    $skip = collect($emitted)->firstWhere('type', 'skip_link');
    expect($formEvent['prompt_text'])->toBe("Now your spouse or partner's details.")
        ->and($formEvent['form']['name'])->toBe('spouse_details')
        ->and($skip['skip_link']['label'])->toBe('Skip this for now')
        ->and($conversation->messages()->latest('id')->first()->metadata['skip_link']['label'])->toBe('Skip this for now');
});

it('saves the spouse from the form, links or invites their account, repeats it back and moves to the dependants question', function (): void {
    Mail::fake();
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_SPOUSE, ['date_of_birth' => '1985-01-12', 'marital_status' => 'married']);
    $conversation = journeyConversation($user);

    $events = submitJourneyForm($user, $conversation, ['name' => 'spouse_details', 'answers' => ['_lead' => [
        'first_name' => 'Jamie', 'last_name' => 'Smith', 'date_of_birth' => '1986-03-03', 'email' => 'jamie-form@example.com', 'annual_income' => 40000,
    ]]]);

    $spouse = FamilyMember::where('user_id', $user->id)->where('relationship', 'spouse')->first();
    expect($spouse)->not->toBeNull()
        ->and($spouse->first_name)->toBe('Jamie')
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('Jamie')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
});

it('an email already in another household is refused on the spouse form and the step does not move', function (): void {
    Mail::fake();
    $thirdParty = User::factory()->create();
    User::factory()->create(['email' => 'taken-spouse@example.com', 'spouse_id' => $thirdParty->id]);
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_SPOUSE, ['date_of_birth' => '1985-01-12', 'marital_status' => 'married']);
    $conversation = journeyConversation($user);

    $events = submitJourneyForm($user, $conversation, ['name' => 'spouse_details', 'answers' => ['_lead' => [
        'first_name' => 'Sam', 'date_of_birth' => '1980-01-01', 'email' => 'taken-spouse@example.com',
    ]]]);

    expect(collect($events)->firstWhere('type', 'capture_form_errors'))->not->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE)
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0);
});

it('saves one dependant from the form, asks for another, re-opens the form without a lead-in on yes and reviews the family on no', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL, ['date_of_birth' => '1985-01-12', 'marital_status' => 'single']);
    $conversation = journeyConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $emitted = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL)), false);
    expect(collect($emitted)->firstWhere('type', 'capture_form')['prompt_text'])->toBe('Lovely. Tell me about them one at a time.');

    $events = submitJourneyForm($user, $conversation, ['name' => 'dependants', 'answers' => ['_lead' => ['relationship' => 'child', 'first_name' => 'Alice', 'date_of_birth' => '2017-09-14']]]);
    $alice = FamilyMember::where('user_id', $user->id)->where('relationship', 'child')->first();
    expect($alice)->not->toBeNull()
        ->and($alice->first_name)->toBe('Alice')
        ->and($alice->date_of_birth->format('Y-m-d'))->toBe('2017-09-14')
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('1 dependant added')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS_MORE)
        ->and(collect($events)->firstWhere('type', 'quick_replies')['prompt_text'])->toBe('Do you have another dependant to add?');

    // Yes: the form again, no lead-in.
    $yes = iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'Yes, add another', null, true), false);
    $reopened = collect($yes)->firstWhere('type', 'capture_form');
    expect($reopened['form']['name'])->toBe('dependants')
        ->and($reopened['prompt_text'])->toBe('')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL);

    $second = submitJourneyForm($user->fresh(), $conversation, ['name' => 'dependants', 'answers' => ['_lead' => ['relationship' => 'parent', 'first_name' => 'June', 'date_of_birth' => '1950-02-01']]]);
    expect(FamilyMember::where('user_id', $user->id)->whereIn('relationship', ['child', 'parent'])->count())->toBe(2)
        ->and(collect($second)->where('type', 'content')->pluck('text')->implode(' '))->toContain('2 dependants added');

    // No: on to the family review exactly where the detail step used to go.
    iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, "No, that's everything", null, true), false);
    expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY);
});

it('emits the work form with the funnel recap for a Save Tax arrival and the short lead-in on the journey path', function (): void {
    $journey = journeyStepUser(OnboardingStateMachine::STATE_BASE_WORK, ['employment_status' => 'employed']);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);
    $emitted = iterator_to_array($director->emitTurnForState($journey, journeyConversation($journey), OnboardingStateMachine::STATE_BASE_WORK, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_WORK)), false);
    $form = collect($emitted)->firstWhere('type', 'capture_form');
    expect($form['prompt_text'])->toBe('Now your work and income.')
        ->and($form['form']['name'])->toBe('work');

    $campaign = User::factory()->create([
        'is_preview_user' => false, 'onboarding_completed' => false, 'first_name' => 'Chris', 'employment_status' => 'employed',
        'annual_employment_income' => null, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'funnel_answers' => ['campaign' => 'savetax', 'employment' => 'employed', 'income' => '75000', 'assets' => ['isa']],
    ]);
    $conversation = journeyConversation($campaign);
    $emitted = iterator_to_array($director->emitTurnForState($campaign, $conversation, OnboardingStateMachine::STATE_BASE_WORK, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_WORK)), false);
    $first = collect($emitted)->firstWhere('type', 'capture_form')['prompt_text'];
    expect($first)->not->toBe('Now your work and income.')
        ->and($first)->toContain('Chris');
    // Delivered once: the resume re-emit gets the short lead-in.
    $again = iterator_to_array($director->emitTurnForState($campaign, $conversation, OnboardingStateMachine::STATE_BASE_WORK, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_WORK)), false);
    expect(collect($again)->firstWhere('type', 'capture_form')['prompt_text'])->toBe('Now your work and income.');
});

it('saves employer, role and income from the work form, repeats the income back and asks about other roles', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_WORK, ['employment_status' => 'employed', 'annual_employment_income' => null]);
    $conversation = journeyConversation($user);

    $events = submitJourneyForm($user, $conversation, ['name' => 'work', 'answers' => ['_lead' => ['employer' => 'Acme Ltd', 'occupation' => 'Software engineer', 'annual_income' => 75000]]]);

    $user->refresh();
    expect($user->employer)->toBe('Acme Ltd')
        ->and($user->occupation)->toBe('Software engineer')
        ->and((float) $user->annual_employment_income)->toBe(75000.0)
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('£75,000 a year, noted')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT_MORE);
});

it('a self-employed user\'s work form income lands on self-employment income', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_BASE_WORK, ['employment_status' => 'self_employed', 'annual_employment_income' => null, 'annual_self_employment_income' => null]);
    submitJourneyForm($user, journeyConversation($user), ['name' => 'work', 'answers' => ['_lead' => ['employer' => 'Self-employed', 'occupation' => 'Consultant', 'annual_income' => 52000]]]);
    expect((float) $user->fresh()->annual_self_employment_income)->toBe(52000.0);
});

// CSJ 2026-09-16: the journey's "Anything else" focuses open on the same forms as the Save Tax walk.
it('picking Savings on Anything else opens the ISA form, and the form states do not skip for a journey user', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_ADD_MORE, ['date_of_birth' => '1985-01-12', 'marital_status' => 'single', 'onboarding_fyn_selection' => 'protection', 'onboarding_fyn_context' => ['visited_focuses' => ['protection']]]);
    $conversation = journeyConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $events = iterator_to_array($director->handleUserMessage($user, $conversation, 'Savings', null, true), false);

    expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS)
        ->and($user->fresh()->onboarding_fyn_selection)->toBe('savings')
        ->and(collect($events)->firstWhere('type', 'capture_form')['form']['name'] ?? null)->toBe('isa');
});

it('saves a life policy from the protection form, asks for another, and "No" verifies the protection page then returns to Anything else', function (): void {
    $user = journeyStepUser(OnboardingStateMachine::STATE_JOURNEY_PROTECTION, ['date_of_birth' => '1985-01-12', 'marital_status' => 'single', 'onboarding_fyn_selection' => 'protection']);
    $conversation = journeyConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);
    $emitted = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_JOURNEY_PROTECTION, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_JOURNEY_PROTECTION)), false);
    expect(collect($emitted)->firstWhere('type', 'capture_form')['prompt_text'])->toBe('Now your protection cover.');

    $events = submitJourneyForm($user, $conversation, ['name' => 'protection', 'answers' => ['life' => ['provider' => 'Aviva', 'sum_assured' => 250000, 'premium_amount' => 25, 'policy_term_years' => 20]]]);
    $policy = LifeInsurancePolicy::where('user_id', $user->id)->first();
    expect($policy)->not->toBeNull()
        ->and((float) $policy->sum_assured)->toBe(250000.0)
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_JOURNEY_PROTECTION_MORE)
        ->and(collect($events)->firstWhere('type', 'quick_replies')['prompt_text'])->toBe('Do you have another policy to add?');

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user->fresh(), $conversation, "No, that's everything", null, true), false);
    expect($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce')
        ->and(OnboardingStateMachine::nextFromVerifyNavigate('yes', $user->fresh()->forceFill(['onboarding_fyn_step' => 'campaign_verify_navigate'])))->toBe(OnboardingStateMachine::STATE_ADD_MORE);
});
