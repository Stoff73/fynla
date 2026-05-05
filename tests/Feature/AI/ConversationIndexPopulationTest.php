<?php

declare(strict_types=1);

use App\Jobs\ConversationSummariserJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\ConversationSummariser;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    config(['services.xai.api_key' => 'test-key']);
});

/**
 * S1.3 — `ConversationSummariserJob` populates the index columns on
 * `ai_conversations` after running through `ConversationSummariser`.
 */
it('summariser populates summary, topics, entities_mentioned, intents_stated, summarised_at', function () {
    $user = User::factory()->create();

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Test',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'metadata' => ['source' => 'fyn_onboarding'],
        'last_message_at' => now()->subMinutes(45),
    ]);

    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'I want to retire at 60 and have a SIPP with Vanguard worth £80,000.',
    ]);

    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Got it — noted your retirement goal and your SIPP balance.',
    ]);

    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'User wants to retire at 60 and holds a Vanguard SIPP worth £80,000.',
                        'topics' => ['retirement'],
                        'entities_mentioned' => [
                            ['type' => 'dc_pension', 'label' => 'Vanguard SIPP'],
                        ],
                        'intents_stated' => ['I want to retire at 60'],
                    ]),
                ],
            ]],
            'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 80],
        ], 200),
    ]);

    app(ConversationSummariser::class)->summarise($conversation->id);

    $conversation->refresh();

    expect($conversation->summary)->toBe('User wants to retire at 60 and holds a Vanguard SIPP worth £80,000.');
    expect($conversation->topics)->toBe(['retirement']);
    expect($conversation->entities_mentioned)->toBe([
        ['type' => 'dc_pension', 'label' => 'Vanguard SIPP'],
    ]);
    expect($conversation->intents_stated)->toBe(['I want to retire at 60']);
    expect($conversation->summarised_at)->not->toBeNull();
});

it('job calls the summariser service with the correct conversation id', function () {
    $user = User::factory()->create();

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Test',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'last_message_at' => now()->subMinutes(45),
    ]);

    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Empty conversation.',
                        'topics' => [],
                        'entities_mentioned' => [],
                        'intents_stated' => [],
                    ]),
                ],
            ]],
        ], 200),
    ]);

    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'hi',
    ]);

    (new ConversationSummariserJob($conversation->id))
        ->handle(app(ConversationSummariser::class));

    $conversation->refresh();

    expect($conversation->summarised_at)->not->toBeNull();
});

it('summariser is a no-op when the api key is not configured', function () {
    config(['services.xai.api_key' => null]);

    $user = User::factory()->create();

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Test',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'last_message_at' => now()->subMinutes(45),
    ]);

    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'hi',
    ]);

    app(ConversationSummariser::class)->summarise($conversation->id);

    $conversation->refresh();

    expect($conversation->summary)->toBeNull();
    expect($conversation->summarised_at)->toBeNull();
});

it('summariser absorbs non-JSON provider responses without persisting anything', function () {
    $user = User::factory()->create();

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Test',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'last_message_at' => now()->subMinutes(45),
    ]);

    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'hi',
    ]);

    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [[
                'message' => ['content' => 'not json at all'],
            ]],
        ], 200),
    ]);

    app(ConversationSummariser::class)->summarise($conversation->id);

    $conversation->refresh();

    expect($conversation->summary)->toBeNull();
    expect($conversation->summarised_at)->toBeNull();
});

it('emitDoneTurn dispatches the summariser job with the conversation id', function () {
    Bus::fake();

    $user = User::factory()->create([
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_ADD_MORE,
        'onboarding_fyn_selection' => 'protection',
        'onboarding_fyn_context' => ['visited_focuses' => ['protection']],
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Onboarding',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'metadata' => ['source' => 'fyn_onboarding'],
        'last_message_at' => now()->subMinutes(45),
    ]);

    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'emitDoneTurn');
    $method->setAccessible(true);

    $generator = $method->invoke($director, $user, $conversation);

    foreach ($generator as $_) {
        // drain the generator so the dispatch at the end runs
    }

    Bus::assertDispatched(
        ConversationSummariserJob::class,
        fn ($job) => $job->conversationId === $conversation->id
    );
});

it('scheduler command dispatches the job for stale conversations', function () {
    Bus::fake();

    $user = User::factory()->create(['onboarding_completed' => true]);

    $stale = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Stale',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'last_message_at' => now()->subHours(2),
    ]);

    $alreadySummarisedRecently = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Recently summarised',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'last_message_at' => now()->subHours(2),
        'summarised_at' => now()->subMinutes(5),
    ]);

    $tooNew = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'In flight',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'last_message_at' => now()->subMinutes(5),
    ]);

    $movedSinceSummary = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Moved on since last summary',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'last_message_at' => now()->subHours(2),
        'summarised_at' => now()->subHours(3),
    ]);

    $this->artisan('ai:conversations:summarise-stale')->assertExitCode(0);

    Bus::assertDispatched(ConversationSummariserJob::class, fn ($job) => $job->conversationId === $stale->id);
    Bus::assertDispatched(ConversationSummariserJob::class, fn ($job) => $job->conversationId === $movedSinceSummary->id);
    Bus::assertNotDispatched(ConversationSummariserJob::class, fn ($job) => $job->conversationId === $alreadySummarisedRecently->id);
    Bus::assertNotDispatched(ConversationSummariserJob::class, fn ($job) => $job->conversationId === $tooNew->id);
});

/**
 * Resume contract — canonical Two-Fyn §0:
 *   "If a user leaves at any point in the conversation, the next time
 *    they log in Onboarding Fyn picks up from where they left off."
 *
 * The scheduler must not summarise an in-flight onboarding conversation
 * mid-flow — see SummariseStaleConversationsCommand resume-contract
 * carve-out. emitDoneTurn fires the dispatch on STATE_DONE; until then,
 * the row is left alone.
 */
it('scheduler skips in-flight onboarding conversations even when idle', function () {
    Bus::fake();

    $midFlow = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_PERSONAL,
    ]);

    $completed = User::factory()->create(['onboarding_completed' => true]);

    $inFlightOnboarding = AiConversation::create([
        'user_id' => $midFlow->id,
        'title' => 'Onboarding in flight',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'metadata' => ['source' => 'fyn_onboarding'],
        'last_message_at' => now()->subHours(4),
    ]);

    // A post-onboarding advice chat for the same user — is also skipped
    // because the user is mid-flow. Once they finish onboarding, both
    // conversations become eligible for the next sweep.
    $advisorChatForMidFlowUser = AiConversation::create([
        'user_id' => $midFlow->id,
        'title' => 'Random chat',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'metadata' => ['source' => 'advice'],
        'last_message_at' => now()->subHours(4),
    ]);

    $eligible = AiConversation::create([
        'user_id' => $completed->id,
        'title' => 'Completed-user chat',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'metadata' => ['source' => 'advice'],
        'last_message_at' => now()->subHours(4),
    ]);

    $this->artisan('ai:conversations:summarise-stale')->assertExitCode(0);

    Bus::assertDispatched(ConversationSummariserJob::class, fn ($job) => $job->conversationId === $eligible->id);
    Bus::assertNotDispatched(ConversationSummariserJob::class, fn ($job) => $job->conversationId === $inFlightOnboarding->id);
    Bus::assertDispatched(ConversationSummariserJob::class, fn ($job) => $job->conversationId === $advisorChatForMidFlowUser->id);
});

/**
 * The resume contract is honoured by the existing
 * `OnboardingChatDirector::getOnboardingStatus` — it pivots on
 * `users.onboarding_fyn_step` + `metadata.source = 'fyn_onboarding'`.
 * S1.3 adds new index columns (summary, topics, ...) but never touches
 * the resume primitives, so a stale summary written to an in-flight
 * conversation does NOT change the resume payload returned to the
 * frontend. This pin keeps S1.3 from accidentally regressing that.
 */
it('resume contract is preserved even when a stale summary has been written to the in-flight conversation', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_EXPENDITURE,
        'onboarding_fyn_path' => 'family',
        'onboarding_fyn_selection' => 'protection',
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Onboarding',
        'status' => 'active',
        'model_used' => 'grok-4-1-fast-reasoning',
        'metadata' => ['source' => 'fyn_onboarding'],
        'last_message_at' => now()->subHours(2),
        // pretend the summariser had run on a partial state at some point
        'summary' => 'Partial: user chose protection focus, gave personal details, pending expenditure capture.',
        'topics' => ['protection'],
        'entities_mentioned' => [],
        'intents_stated' => ['wants protection cover review'],
        'summarised_at' => now()->subHour(),
    ]);

    $director = app(OnboardingChatDirector::class);
    $status = $director->getOnboardingStatus($user);

    expect($status['in_progress'])->toBeTrue();
    expect($status['current_step'])->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
    expect($status['path'])->toBe('family');
    expect($status['selection'])->toBe('protection');
    expect($status['conversation_id'])->toBe($conversation->id);
});
