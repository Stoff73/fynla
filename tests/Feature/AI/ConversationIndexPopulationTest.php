<?php

declare(strict_types=1);

use App\Jobs\ConversationSummariserJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\ConversationSummariser;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

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
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
        'last_message_at' => now()->subHours(2),
    ]);

    $alreadySummarisedRecently = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Recently summarised',
        'status' => 'active',
        'model_used' => 'grok-4.3',
        'last_message_at' => now()->subHours(2),
        'summarised_at' => now()->subMinutes(5),
    ]);

    $tooNew = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'In flight',
        'status' => 'active',
        'model_used' => 'grok-4.3',
        'last_message_at' => now()->subMinutes(5),
    ]);

    $movedSinceSummary = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Moved on since last summary',
        'status' => 'active',
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
        'metadata' => ['source' => 'advice'],
        'last_message_at' => now()->subHours(4),
    ]);

    $eligible = AiConversation::create([
        'user_id' => $completed->id,
        'title' => 'Completed-user chat',
        'status' => 'active',
        'model_used' => 'grok-4.3',
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
        'model_used' => 'grok-4.3',
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

it('projects onboarding, contextual, and legacy conversations into safe ordered history', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = SavingsAccount::factory()->for($user)->create([
        'account_name' => 'Rainy Day Account',
        'current_balance' => 12500,
    ]);

    $onboarding = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Your Fynla setup',
        'status' => 'paused',
        'model_used' => 'test',
        'message_count' => 1,
        'last_message_at' => now()->subMinutes(20),
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);
    AiMessage::create([
        'conversation_id' => $onboarding->id,
        'role' => 'assistant',
        'content' => 'Let us continue setting up your plan.',
    ]);

    $legacy = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'General question',
        'status' => 'active',
        'model_used' => 'test',
        'message_count' => 1,
        'last_message_at' => now()->subMinutes(10),
        'metadata' => null,
    ]);
    AiMessage::create([
        'conversation_id' => $legacy->id,
        'role' => 'user',
        'content' => 'How should I think about emergency savings?',
    ]);

    $contextual = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Edit Rainy Day Account',
        'status' => 'active',
        'model_used' => 'test',
        'message_count' => 2,
        'last_message_at' => now()->subMinute(),
        'metadata' => [
            'source' => 'surface_action',
            'mode' => 'surface_action',
            'action' => 'edit',
            'resource_type' => 'savings_account',
            'resource_id' => $account->id,
            'current_destination' => [
                'screen' => 'savings_account_detail',
                'params' => ['account_id' => $account->id],
                'fallback' => 'savings',
            ],
        ],
    ]);
    AiMessage::create([
        'conversation_id' => $contextual->id,
        'role' => 'assistant',
        'content' => 'Your balance is £184,500 and the account number is 12345678.',
    ]);
    AiMessage::create([
        'conversation_id' => $contextual->id,
        'role' => 'system',
        'content' => 'SYSTEM CONTENT MUST NOT APPEAR',
    ]);

    AiConversation::create([
        'user_id' => $otherUser->id,
        'title' => 'Another user conversation',
        'status' => 'active',
        'model_used' => 'test',
        'last_message_at' => now(),
    ]);
    AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Archived conversation',
        'status' => 'archived',
        'model_used' => 'test',
        'last_message_at' => now(),
    ]);

    Sanctum::actingAs($user);
    $response = $this->getJson('/api/ai-chat/conversations')->assertOk();
    $history = collect($response->json('data'));

    expect($history->pluck('id')->all())->toBe([
        $contextual->id,
        $legacy->id,
        $onboarding->id,
    ]);

    $contextualItem = $history->firstWhere('id', $contextual->id);
    expect($contextualItem)
        ->toMatchArray([
            'mode' => 'contextual',
            'purpose' => 'Edit Bank Account',
            'status' => 'active',
            'related_entity' => [
                'type' => 'savings_account',
                'id' => $account->id,
                'label' => 'Rainy Day Account',
                'available' => true,
                'explanation' => null,
            ],
        ])
        ->and($contextualItem['fallback_destination']['screen'])->toBe('savings')
        ->and($contextualItem['last_message_summary'])->toBe('Continue this contextual conversation with Fyn.')
        ->and($contextualItem['last_message_summary'])->not->toContain('184,500')
        ->and($contextualItem['last_message_summary'])->not->toContain('12345678')
        ->and($contextualItem['last_message_summary'])->not->toContain('SYSTEM CONTENT')
        ->and($contextualItem['created_at'])->toBeString()
        ->and($contextualItem['updated_at'])->toBeString();

    expect($history->firstWhere('id', $legacy->id)['mode'])->toBe('general')
        ->and($history->firstWhere('id', $onboarding->id)['mode'])->toBe('onboarding');

    $account->delete();
    $unavailable = collect(
        $this->getJson('/api/ai-chat/conversations')->assertOk()->json('data'),
    )->firstWhere('id', $contextual->id);

    expect($unavailable['related_entity']['available'])->toBeFalse()
        ->and($unavailable['related_entity']['label'])->toBeNull()
        ->and($unavailable['related_entity']['explanation'])
        ->toBe('This related item is no longer available.')
        ->and($unavailable['fallback_destination']['screen'])->toBe('savings');
});

it('batches contextual history availability checks by resource type', function (): void {
    $user = User::factory()->create();
    $accounts = SavingsAccount::factory()->count(3)->for($user)->create();

    foreach ($accounts as $account) {
        AiConversation::create([
            'user_id' => $user->id,
            'title' => 'Edit account',
            'status' => 'active',
            'model_used' => 'test',
            'last_message_at' => now(),
            'metadata' => [
                'source' => 'surface_action',
                'action' => 'edit',
                'resource_type' => 'savings_account',
                'resource_id' => $account->id,
            ],
        ]);
    }

    Sanctum::actingAs($user);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson('/api/ai-chat/conversations')->assertOk();

    $savingsQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'savings_accounts'));

    expect($savingsQueries)->toHaveCount(1);
});
