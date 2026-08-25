<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Structured turn intent (Task 1) — every assistant persist carries
 * metadata.turn_intent, written by the emitter that KNOWS what it is
 * emitting. One representative flow per intent family; read-side
 * consumers switch in Tasks 2-4. Spec:
 * docs/superpowers/specs/2026-07-22-structured-turn-intent-and-gate-facts.md
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function turnIntentUser(string $step = OnboardingStateMachine::STATE_PATH_CHOICE): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => $step,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['source' => 'fyn_onboarding'],
        'message_count' => 0,
    ]);

    return [$user, $conversation];
}

function drainDirectorAction(User $user, AiConversation $conversation, string $action): void
{
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, $action) as $_) {
        // drain
    }
}

function drainDirectorMessage(User $user, AiConversation $conversation, string $message): void
{
    foreach (app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, $message) as $_) {
        // drain
    }
}

it('stamps resume_greeting on the welcome-back greeting', function () {
    [$user, $conversation] = turnIntentUser(OnboardingStateMachine::STATE_BASE_DEPENDANTS);

    drainDirectorAction($user, $conversation, 'resume');

    $greeting = $conversation->messages()
        ->where('metadata->is_resume_greeting', true)
        ->first();
    expect($greeting)->not->toBeNull();
    expect($greeting->metadata['turn_intent'] ?? null)->toBe('resume_greeting');
});

it('stamps step_prompt on a re-emitted state turn', function () {
    [$user, $conversation] = turnIntentUser();

    drainDirectorAction($user, $conversation, 'continue');

    $prompt = $conversation->messages()
        ->where('role', 'assistant')
        ->where('metadata->onboarding_step', OnboardingStateMachine::STATE_PATH_CHOICE)
        ->latest('id')
        ->first();
    expect($prompt)->not->toBeNull();
    expect($prompt->metadata['turn_intent'] ?? null)->toBe('step_prompt');
});

it('stamps interruption_offer on the store offer', function () {
    [$user, $conversation] = turnIntentUser();

    drainDirectorMessage($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $offer = $conversation->messages()
        ->where('role', 'assistant')
        ->where('content', 'like', '%worth saving%')
        ->first();
    expect($offer)->not->toBeNull();
    expect($offer->metadata['turn_intent'] ?? null)->toBe('interruption_offer');
});

it('stamps deferred_promise on the defer promise and step_prompt on the re-emitted walk step', function () {
    [$user, $conversation] = turnIntentUser();

    drainDirectorMessage($user, $conversation, 'How is my financial health?');

    $promise = $conversation->messages()
        ->where('role', 'assistant')
        ->where('content', 'like', '%once your setup is done%')
        ->first();
    expect($promise)->not->toBeNull();
    expect($promise->metadata['turn_intent'] ?? null)->toBe('deferred_promise');

    $reEmitted = $conversation->messages()
        ->where('role', 'assistant')
        ->where('metadata->onboarding_step', OnboardingStateMachine::STATE_PATH_CHOICE)
        ->latest('id')
        ->first();
    expect($reEmitted->metadata['turn_intent'] ?? null)->toBe('step_prompt');
});

it('refines a no-tool clarification capture turn from capture_ack to capture_clarification', function () {
    // The shared pipeline stamps every data_capture persist capture_ack by
    // default. A turn that captured nothing and asked for a missing fact is
    // a clarification — the director must refine the stamp so Task 2-4
    // readers (which prefer the enum) still arm the followup.
    [$user, $conversation] = turnIntentUser();

    // Arm pending_interruption_store via the deterministic offer path.
    drainDirectorMessage($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $clarification = 'Is the account owned by just you, or held jointly? I need that before I can save it.';
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function () use ($clarification, $conversation) {
            // Mimic HasAiChat: persist the streamed text with the default
            // capture_ack stamp the pipeline writes for data_capture turns.
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $clarification,
                'metadata' => ['turn_intent' => 'capture_ack'],
            ]);
            yield ['type' => 'content', 'text' => $clarification];
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    app()->instance(CoordinatingAgent::class, $mock);

    drainDirectorMessage($user->refresh(), $conversation, 'Yes, save it');

    $row = $conversation->messages()
        ->where('role', 'assistant')
        ->where('content', $clarification)
        ->first();
    expect($row)->not->toBeNull();
    expect($row->metadata['turn_intent'] ?? null)->toBe('capture_clarification');
});

it('stamps deferred_raise and celebration on the done turn', function () {
    [$user, $conversation] = turnIntentUser();
    $conversation->update(['metadata' => array_merge($conversation->metadata ?? [], [
        'deferred_questions' => [['question' => 'How healthy are my overall finances?', 'state_id' => 'path_choice']],
    ])]);

    $method = new ReflectionMethod(OnboardingChatDirector::class, 'emitDoneTurn');
    foreach ($method->invoke(app(OnboardingChatDirector::class), $user, $conversation) as $_) {
        // drain
    }

    $raise = $conversation->messages()
        ->where('content', 'like', '%You asked about%')
        ->first();
    expect($raise)->not->toBeNull();
    expect($raise->metadata['turn_intent'] ?? null)->toBe('deferred_raise');

    $celebration = $conversation->messages()
        ->where('metadata->onboarding_step', OnboardingStateMachine::STATE_DONE)
        ->first();
    expect($celebration)->not->toBeNull();
    expect($celebration->metadata['turn_intent'] ?? null)->toBe('celebration');
});
