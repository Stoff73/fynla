<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * T20 (walk 2026-10-11, P8): "Please add my two children: Maya, who is 11, and
 * Arjun, who is 8. They both depend on me and Dev." (#3977) opened one form,
 * for Maya (#3978); Arjun was dropped until the user said "You missed Arjun"
 * (#3981). Each person named gets a form: the first opens filled in, and
 * saving it opens the next, filled in, in chat and in the setup walk alike.
 * Only the newest form is ever open on web and /m, so they come one at a time.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
    FynStreamHarness::fake()->bind(); // no turns queued: any chat model call fails the test
    config(['services.xai.api_key' => 'test']);
});

afterEach(function (): void {
    Mockery::close();
});

function twoChildrenRead(): void
{
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['forms' => ['0' => [
        ['_lead' => ['relationship' => 'child', 'first_name' => 'Maya']],
        ['_lead' => ['relationship' => 'child', 'first_name' => 'Arjun']],
    ]]])]]]])]);
}

function personFormsDirector(): OnboardingChatDirector
{
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    return $director;
}

function personFormsConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Fyn'])->fresh();
}

/** @param  array<string, mixed>  $lead */
function submitDependantForm(User $user, AiConversation $conversation, array $lead): array
{
    $form = ['name' => CaptureForms::DEPENDANTS, 'answers' => [CaptureForms::LEAD => $lead]];

    return iterator_to_array(personFormsDirector()->handleUserMessage($user->fresh(), $conversation, CaptureForms::summarise($form), null, true, $form), false);
}

it('opens a form for each child named in chat, one after the other, each filled in', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'onboarding_fyn_step' => null, 'surname' => 'Sandhu']);
    $conversation = personFormsConversation($user);
    twoChildrenRead();

    $events = iterator_to_array(personFormsDirector()->offerTypedForm(
        $user, $conversation, 'Please add my two children: Maya, who is 11, and Arjun, who is 8. They both depend on me and Dev.',
        [], [CaptureForms::DEPENDANTS], openAnyway: true,
    ), false);

    $first = collect($events)->firstWhere('type', 'capture_form');
    expect($first['values'])->toBe([CaptureForms::LEAD => ['relationship' => 'child', 'first_name' => 'Maya']])
        ->and($first['prompt_text'])->toBe(CaptureForms::filledPrompt(1))
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0);

    // Save Maya: she is written, and Arjun's form opens filled in.
    $afterMaya = submitDependantForm($user, $conversation, ['relationship' => 'child', 'first_name' => 'Maya', 'date_of_birth' => '2015-03-14', 'is_disabled' => 'no']);
    $second = collect($afterMaya)->firstWhere('type', 'capture_form');
    expect(FamilyMember::where('user_id', $user->id)->pluck('first_name')->all())->toBe(['Maya'])
        ->and(collect($afterMaya)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second['values'])->toEqual([CaptureForms::LEAD => ['relationship' => 'child', 'first_name' => 'Arjun']]) // JSON column: key order not kept
        ->and($second['prompt_text'])->toBe(CaptureForms::FILLED_NEXT_PROMPT);

    // Save Arjun: written, and nothing more opens.
    $afterArjun = submitDependantForm($user, $conversation, ['relationship' => 'child', 'first_name' => 'Arjun', 'date_of_birth' => '2018-09-02', 'is_disabled' => 'no']);
    expect(FamilyMember::where('user_id', $user->id)->orderBy('id')->pluck('first_name')->all())->toBe(['Maya', 'Arjun'])
        ->and(collect($afterArjun)->firstWhere('type', 'capture_form'))->toBeNull()
        ->and(collect($afterArjun)->last()['type'])->toBe('done');
});

it('keeps the next child queued when a save is refused, so the corrected save still opens it', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'onboarding_fyn_step' => null]);
    $conversation = personFormsConversation($user);
    twoChildrenRead();
    iterator_to_array(personFormsDirector()->offerTypedForm($user, $conversation, 'Add my children Maya and Arjun', [], [CaptureForms::DEPENDANTS], openAnyway: true), false);

    // No date of birth: refused, nothing written.
    $refused = submitDependantForm($user, $conversation, ['relationship' => 'child', 'first_name' => 'Maya']);
    expect(collect($refused)->firstWhere('type', 'capture_form_errors'))->not->toBeNull()
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0);

    $corrected = submitDependantForm($user, $conversation, ['relationship' => 'child', 'first_name' => 'Maya', 'date_of_birth' => '2015-03-14']);
    expect(collect($corrected)->firstWhere('type', 'capture_form')['values'][CaptureForms::LEAD]['first_name'] ?? null)->toBe('Arjun');
});

it('opens the next child named at the setup walk\'s dependants form instead of asking for another', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false, 'onboarding_completed' => false, 'first_name' => 'Chris',
        'date_of_birth' => '1985-01-12', 'marital_status' => 'single',
        'onboarding_fyn_path' => 'journey', 'onboarding_fyn_selection' => 'savings',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL,
    ]);
    $conversation = personFormsConversation($user);
    twoChildrenRead();

    $typed = iterator_to_array(personFormsDirector()->handleUserMessage($user, $conversation, 'Maya, who is 11, and Arjun, who is 8'), false);
    expect(collect($typed)->firstWhere('type', 'capture_form')['values'][CaptureForms::LEAD]['first_name'] ?? null)->toBe('Maya')
        ->and(collect($typed)->firstWhere('type', 'capture_form')['prompt_text'])->toBe(CaptureForms::filledPrompt(1));

    $afterMaya = submitDependantForm($user, $conversation, ['relationship' => 'child', 'first_name' => 'Maya', 'date_of_birth' => '2015-03-14']);
    $second = collect($afterMaya)->firstWhere('type', 'capture_form');
    expect($second['values'][CaptureForms::LEAD]['first_name'] ?? null)->toBe('Arjun')
        ->and($second['prompt_text'])->toBe(CaptureForms::FILLED_NEXT_PROMPT)
        ->and(collect($afterMaya)->firstWhere('type', 'quick_replies'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL);

    // The last one saved: the walk asks for another, as after any dependant.
    $afterArjun = submitDependantForm($user, $conversation, ['relationship' => 'child', 'first_name' => 'Arjun', 'date_of_birth' => '2018-09-02']);
    expect(FamilyMember::where('user_id', $user->id)->count())->toBe(2)
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS_MORE)
        ->and(collect($afterArjun)->firstWhere('type', 'quick_replies')['prompt_text'])->toBe('Do you have another dependant to add?');
});
