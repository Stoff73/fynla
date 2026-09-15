<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Property;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

afterEach(function (): void {
    Mockery::close();
});

function formStepUser(string $step = OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY): User
{
    return User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'marital_status' => 'married',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => $step,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property', 'investments']],
    ]);
}

function formConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
}

it('emits the property form to a client that can render forms and persists the schema for history', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $events = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)), false);

    $form = collect($events)->firstWhere('type', 'capture_form');
    expect($form)->not->toBeNull()
        ->and($form['form'])->toBe(CaptureForms::schema('property'))
        ->and($form['prompt_text'])->toContain('Now your property')
        ->and(collect($events)->where('type', 'content'))->toHaveCount(0)
        ->and(collect($events)->last()['type'])->toBe('done');

    // ->toEqual, not ->toBe, for capture_form: MySQL's native JSON column
    // type alphabetically re-sorts object keys at every nesting level on
    // round-trip (verified directly against the column: {"b":1,"a":2}
    // written comes back {"a":2,"b":1}) — a storage-engine fact, not a bug
    // in this feature. The schema is unaffected value-for-value.
    $saved = AiMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->latest('id')->first();
    expect($saved->metadata['capture_form'])->toEqual(CaptureForms::schema('property'))
        ->and($saved->metadata['onboarding_step'])->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)
        ->and($saved->metadata['turn_intent'])->toBe('step_prompt');
});

it('emits the typed prompt instead when the client has not declared forms — native stays as it was', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(false);

    $events = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)), false);

    expect(collect($events)->firstWhere('type', 'capture_form'))->toBeNull()
        ->and(collect($events)->firstWhere('type', 'content')['text'])->toContain('Now your property');
});

it('sends a typed sentence at the form step down the existing capture path', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()
        ->toolTurn('create_property', ['property_type' => 'buy_to_let', 'current_value' => 450000, 'has_mortgage' => true, 'mortgage_outstanding_balance' => 100000, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'], 'toolu_btl')
        ->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'A buy to let worth 450000, mortgage 100000, rent 1000 a month, mine'), false);

    expect(Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->exists())->toBeTrue();
});

function propertyAnswers(array $overrides = []): array
{
    return ['name' => 'property', 'answers' => array_replace_recursive([
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
        'buy_to_let' => ['current_value' => 450000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'],
    ], $overrides)];
}

it('saves both kinds from a form answer with no model call and advances to verify', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()
    ), false);

    $home = Property::where('user_id', $user->id)->where('property_type', 'main_residence')->first();
    $btl = Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->first();
    expect($home)->not->toBeNull()
        ->and((float) $home->current_value)->toBe(750000.0)
        ->and((float) $home->outstanding_mortgage)->toBe(325000.0)
        ->and($home->ownership_type)->toBe('joint')
        ->and((float) $home->ownership_percentage)->toBe(50.0)
        ->and($home->tenure_type)->toBe('freehold')
        ->and($btl)->not->toBeNull()
        ->and((float) $btl->current_value)->toBe(450000.0)
        ->and((float) $btl->outstanding_mortgage)->toBe(0.0)
        ->and((float) $btl->monthly_rental_income)->toBe(1000.0)
        ->and($btl->ownership_type)->toBe('individual')
        ->and(collect($events)->where('type', 'entity_created'))->toHaveCount(2)
        ->and(collect($events)->first()['type'])->toBe('form_received')
        ->and(collect($events)->first()['text'])->toBe(CaptureForms::summarise(propertyAnswers()))
        ->and(collect($events)->firstWhere('type', 'capture_complete'))->not->toBeNull()
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce');

    // ->toEqual, not ->toBe, for metadata['form'] — the same MySQL native
    // JSON column key-reordering fact documented above for capture_form.
    $userRow = AiMessage::where('conversation_id', $conversation->id)->where('role', 'user')->latest('id')->first();
    expect($userRow->content)->toContain('Home worth £750,000')
        ->and($userRow->metadata['form'])->toEqual(propertyAnswers());
});

it('saves one kind alone', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();
    $form = ['name' => 'property', 'answers' => ['buy_to_let' => ['current_value' => 200000, 'mortgage_outstanding_balance' => 50000, 'monthly_rental_income' => 850, 'ownership_type' => 'tenants_in_common', 'ownership_percentage' => 60]]];

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise($form), null, true, $form), false);

    $btl = Property::where('user_id', $user->id)->first();
    expect(Property::where('user_id', $user->id)->count())->toBe(1)
        ->and($btl->ownership_type)->toBe('tenants_in_common')
        ->and((float) $btl->ownership_percentage)->toBe(60.0)
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce');
});

it('reports a refused kind on the form, keeps the landed one, and stays on the step', function (): void {
    // Free holds two properties; a third is refused by the tier cap.
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();
    Property::create(['user_id' => $user->id, 'property_type' => 'secondary_residence', 'current_value' => 100000, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'address_line_1' => 'Second home', 'city' => 'Unknown', 'postcode' => 'N/A']);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()
    ), false);

    $errors = collect($events)->firstWhere('type', 'capture_form_errors');
    expect(Property::where('user_id', $user->id)->where('property_type', 'main_residence')->exists())->toBeTrue()
        ->and(Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->exists())->toBeFalse()
        ->and($errors)->not->toBeNull()
        ->and($errors['errors'])->toHaveKey('buy_to_let')
        ->and($errors['errors']['buy_to_let']['message'])->toContain('property limit')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('Buy to let')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);
});

it('never invokes the model for a form answer', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()), false);

    expect(AiMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->whereNotNull('tool_calls')->exists())->toBeFalse();
});

it('tells the user a stale form is no longer open and re-emits the current step', function (): void {
    $user = formStepUser(OnboardingStateMachine::STATE_CAMPAIGN_DOB);
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'Home worth £1', null, true, propertyAnswers()), false);

    expect(Property::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('no longer open')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('date of birth')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_DOB);
});

it('never advances on a form answer with no recognised kind', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, '', null, true, ['name' => 'property', 'answers' => ['castle' => ['current_value' => 1]]]
    ), false);

    expect(Property::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(collect($events)->firstWhere('type', 'capture_form_errors')['errors'])->toHaveKey('_form')
        ->and(collect($events)->firstWhere('type', 'capture_complete'))->toBeNull()
        ->and(collect($events)->firstWhere('type', 'onboarding_advance'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);
});
