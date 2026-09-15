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
