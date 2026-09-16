<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\FamilyMember;
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
