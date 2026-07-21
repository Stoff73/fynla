<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\QueryClassifier;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\FynStreamHarness;
use Tests\Support\Fyn\ScriptedAnthropicClient;

uses(RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

/**
 * Scripts the shared FynLoop advice turn with one text answer.
 *
 * FynLoop::run() makes TWO scripted LLM calls in sequence against the same
 * client — the planner's forced `plan` tool call, then the reasoner's
 * streamed answer — so the harness must queue both turns (see
 * tests/Feature/Fyn/FynLoopPlannerTest.php's "plans reason then streams the
 * reasoner answer" case, the canonical precedent for this exact two-turn
 * shape). FynStreamHarness::bind() forces the Anthropic provider and binds
 * Anthropic\Client — the idiom every FynLoop-driving suite uses.
 */
function scriptedFynClientWithText(string $text): void
{
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->textTurn($text)
        ->bind();
}

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function interruptionUser(string $step = OnboardingStateMachine::STATE_PATH_CHOICE): array
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

function driveDirector(User $user, AiConversation $conversation, string $message): array
{
    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, $message) as $event) {
        $received[] = $event;
    }

    return $received;
}

/**
 * Scripts CoordinatingAgent::chatWithPromptOverride directly — the same
 * idiom tests/Feature/Fyn/CaptureTurnAnswersQuestionsTest.php's
 * driveCaptureTurn uses for the delegated/grouped_extract capture turns.
 * handleInlineCapture (the store-offer "yes" / awaiting_detail path) drives
 * the identical entry point via FynLoop::stream(), so the mock applies here
 * unchanged.
 *
 * Unlike the plain mock in CaptureTurnAnswersQuestionsTest.php, this variant
 * ALSO persists a real assistant AiMessage row for every non-empty content
 * event — mirroring what the real chatWithPromptOverride implementation
 * does — because OnboardingChatDirector::resolvePendingInterruptionCapture
 * inspects "the latest assistant message" in the conversation to decide
 * whether to re-arm pending_interruption_store. Without a persisted row that
 * lookup would silently fall back to a stale earlier message.
 *
 * @param  list<array<string, mixed>>  $events
 * @return array{events: list<array<string, mixed>>, captured_message: ?string}
 */
function driveDirectorWithScriptedCaptureTurn(User $user, AiConversation $conversation, string $message, array $events): array
{
    $capturedMessage = null;
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function (
            User $userArg,
            AiConversation $conversationArg,
            string $messageArg,
            ?string $currentRoute = null,
            ?string $systemPromptOverride = null,
            ?array $allowedTools = null,
            bool $persistUserMessage = true,
            ?array $toolsListOverride = null,
            ?string $personaOverride = null,
            ?string $providerOverride = null,
        ) use (&$capturedMessage, $events, $conversation) {
            $capturedMessage = $messageArg;
            foreach ($events as $event) {
                if (($event['type'] ?? null) === 'content' && trim((string) ($event['text'] ?? '')) !== '') {
                    $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $event['text'],
                    ]);
                }
                yield $event;
            }
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    app()->instance(CoordinatingAgent::class, $mock);

    $received = driveDirector($user, $conversation, $message);

    return ['events' => $received, 'captured_message' => $capturedMessage];
}

it('still emits the plain retry for unclassifiable free text', function () {
    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'asdf qwerty');

    $texts = collect($received)->where('type', 'content')->pluck('text');
    expect($texts->first(fn ($t) => str_contains($t, "didn't catch that")))->not->toBeNull();
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});

it('offers to store volunteered write-intent information and parks the pending flag', function () {
    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $offer = collect($received)->firstWhere('type', 'quick_replies');
    expect($offer)->not->toBeNull();
    expect($offer['prompt_text'])->toContain('save');
    expect(array_column($offer['bubbles'], 'id'))->toBe(['store_now', 'store_later']);

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store']['message'] ?? null)
        ->toBe('I have a Cash ISA with Barclays with £30,000 in it');
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);

    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect(array_column($persisted->metadata['bubbles'] ?? [], 'id'))->toBe(['store_now', 'store_later']);
});

it('declining the store offer resumes the walk and keeps nothing pending', function () {
    [$user, $conversation] = interruptionUser();
    driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $received = driveDirector($user->refresh(), $conversation, 'Not now');

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    // The walk's current question is re-emitted.
    expect(collect($received)->where('type', 'quick_replies')->count())->toBeGreaterThan(0);
});

it('accepting the store offer routes the original message through inline capture', function () {
    $this->seed(TierConfigurationSeeder::class);
    [$user, $conversation] = interruptionUser();
    driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $received = driveDirector($user->refresh(), $conversation, 'Yes, save it');

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
    // Seam assertion fallback (see brief NOTE): the deterministic gap-fill
    // extractor DOES parse this message into a Cash ISA input, but
    // CaptureAccuracyGate legitimately blocks the write with a
    // clarification_required error because the extractor cannot supply
    // ownership_type for an ISA (Rule 6 — joint ISAs are illegal, so ISA
    // ownership must always be explicit, never assumed) — so no
    // SavingsAccount row is stub-dependently persisted here. Assert the
    // seam instead: the pending flag was consumed and a capture
    // acknowledgement was voiced by the inline-capture turn.
    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->not->toContain("didn't catch that");
    expect(collect($received)->where('type', 'tool_use')->pluck('tool'))->toContain('create_savings_account');
});

// ── Fix: store-offer clarification loops back into inline capture instead
// of dead-ending (live conversation 164, msg 19433) ────────────────────────

it('re-arms the store offer with awaiting_detail when the capture turn asks for a missing detail instead of burying it under the walk step', function () {
    [$user, $conversation] = interruptionUser();
    driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $clarification = 'I need to confirm ownership before I can save this — is it individually owned by you?';
    $result = driveDirectorWithScriptedCaptureTurn($user->refresh(), $conversation, 'Yes, save it', [
        ['type' => 'content', 'text' => $clarification],
        ['type' => 'done', 'message_id' => 999],
    ]);
    $received = $result['events'];

    // The clarification question is passed straight through as the live
    // question — it must not be buried under a re-emitted walk step.
    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->toContain('individually owned');
    expect(collect($received)->where('type', 'quick_replies'))->toBeEmpty();

    $user->refresh();
    $pending = $user->onboarding_fyn_context['pending_interruption_store'] ?? null;
    expect($pending)->not->toBeNull();
    expect($pending['awaiting_detail'] ?? null)->toBeTrue();
    expect($pending['message'] ?? null)->toBe('I have a Cash ISA with Barclays with £30,000 in it');
    expect($pending['state_id'] ?? null)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);

    // The walk step pointer itself never moved.
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});

it('routes the missing-detail reply into inline capture as the merged message and resumes the walk once the capture turn succeeds', function () {
    [$user, $conversation] = interruptionUser();
    driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $clarification = 'I need to confirm ownership before I can save this — is it individually owned by you?';
    driveDirectorWithScriptedCaptureTurn($user->refresh(), $conversation, 'Yes, save it', [
        ['type' => 'content', 'text' => $clarification],
        ['type' => 'done', 'message_id' => 999],
    ]);

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store']['awaiting_detail'] ?? null)->toBeTrue();

    // This time the capture turn succeeds — no further clarification.
    $result = driveDirectorWithScriptedCaptureTurn($user->refresh(), $conversation, 'Yes, individually owned by me', [
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'complete'],
        ['type' => 'content', 'text' => 'Recorded — Cash ISA with Barclays.'],
        ['type' => 'done', 'message_id' => 1000],
    ]);
    $received = $result['events'];

    // handleInlineCapture ran with the merged message, not the bare detail
    // reply — the exact format the delegated-turn merge machinery uses
    // (mergeUnresolvedCaptureMessage / captureResponseRequestsClarification).
    expect($result['captured_message'])->toBe(
        "Original capture details: I have a Cash ISA with Barclays with £30,000 in it\n"
        .'Requested missing details: Yes, individually owned by me'
    );

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();

    // The walk resumed — its current step was re-emitted after the
    // successful capture.
    expect(collect($received)->where('type', 'quick_replies'))->not->toBeEmpty();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});

// ── Fix: deterministic ownership gap-fill rescues gate-blocked captures
// (live conversation 164, user 271) ─────────────────────────────────────
//
// grok called create_savings_account with institution/account_type/balance/
// interest_rate but NO ownership_type on FOUR consecutive turns, even after
// the user stated ownership explicitly — the model pattern-locks on its own
// prior failing tool call. CaptureAccuracyGate correctly blocked every
// attempt (missing ownership_type). AssetCaptureEntityExtractor now parses
// ownership from the user's own words and the gap-fill merges it onto the
// LLM's otherwise-correct fields, so the retry the LLM itself still gets
// wrong is rescued deterministically.

it('rescues a gate-blocked create_savings_account via deterministic ownership gap-fill when the model omits ownership_type again on the retry', function () {
    $this->seed(TierConfigurationSeeder::class);
    [$user, $conversation] = interruptionUser();

    // Turn 1: user volunteers a savings account mid-onboarding. Pure
    // classifier path (handleInformationInterruption) — no LLM call.
    driveDirector($user, $conversation, 'I have £15,000 in a Halifax savings account at 4.1% interest');

    // Simulate having already been asked once for ownership — skips the
    // "Yes, save it" acceptance turn (irrelevant to the mechanism under
    // test, and it would otherwise add an unrelated "yes, save it" segment
    // to CaptureAccuracyGate's multi-turn evidence window).
    $user->refresh();
    $context = $user->onboarding_fyn_context;
    $context['pending_interruption_store']['awaiting_detail'] = true;
    $user->onboarding_fyn_context = $context;
    $user->save();

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);

    // Turn 2: the user answers with explicit individual-ownership wording.
    // The model pattern-locks on its own prior failing tool call and OMITS
    // ownership_type AGAIN — the deterministic gap-fill must rescue it by
    // merging the extractor's ownership_type onto the LLM's own (otherwise
    // correct) institution/balance/interest_rate fields.
    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', [
            'account_name' => 'Halifax Savings Account',
            'account_type' => 'easy_access',
            'institution' => 'Halifax',
            'current_balance' => 15000.0,
            'interest_rate' => 4.1,
        ])
        ->textTurn('Understood.')
        ->bind();

    $received = driveDirector($user->refresh(), $conversation, "Yes, it's owned individually by me");

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and($account->institution)->toBe('Halifax')
        ->and((float) $account->current_balance)->toEqual(15000.0)
        ->and($account->ownership_type)->toBe('individual');

    // The pending flag is consumed (not re-armed) — the rescued write is
    // never mistaken for a still-unresolved clarification.
    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();

    // The walk resumed — its current step was re-emitted.
    expect(collect($received)->where('type', 'quick_replies'))->not->toBeEmpty();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});

it('answers a module-level question inline from data then resumes the walk', function () {
    $this->seed(TierConfigurationSeeder::class);
    // Scripted advice turn: one text chunk. The global Pest hook binds an
    // empty scripted client; rebind with a single content event so the
    // FynLoop advice turn voices an answer.
    scriptedFynClientWithText('Your pension is on track — you hold one workplace pension.');

    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'Am I on track for retirement?');

    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->toContain('pension');
    expect($texts)->not->toContain("didn't catch that");

    // The walk resumed at the same step.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);

    // Event-ordering hard gate: the advice answer's content event must
    // arrive strictly before the re-emitted step's quick_replies event, and
    // there must be exactly one terminal `done` — the one closing the
    // re-emitted step, not the FynLoop advice turn's own terminal marker
    // (which would otherwise end the SSE turn before the walk re-renders).
    $types = collect($received)->pluck('type');
    $contentIndex = $types->search('content');
    $quickRepliesIndex = $types->search('quick_replies');
    expect($contentIndex)->not->toBeFalse();
    expect($quickRepliesIndex)->not->toBeFalse();
    expect($quickRepliesIndex)->toBeGreaterThan($contentIndex);
    expect($types->filter(fn ($t) => $t === 'done'))->toHaveCount(1);
});

it('defers a holistic question with a promise and parks it on the conversation', function () {
    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'How is my financial health?');

    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->toContain('once your setup is done');

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'][0]['question'] ?? null)
        ->toBe('How is my financial health?');
    expect($conversation->metadata['deferred_questions'][0]['state_id'] ?? null)
        ->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    // Never clobber the resume-lookup pivot already on the conversation.
    expect($conversation->metadata['source'] ?? null)->toBe('fyn_onboarding');

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);

    // The walk's current question is re-emitted after the promise.
    expect(collect($received)->where('type', 'quick_replies')->count())->toBeGreaterThan(0);
});

it('falls through to the plain retry when a question-shaped message does not classify', function () {
    // QueryClassifier::classify() always returns a string `primary`
    // (defaulting to `general`), so no real phrasing drives the
    // `$primary === null` branch in handleQuestionInterruption — mock it
    // directly, the same pattern AdviceFynHandoffErrorTest and
    // AssistantHonestyOnWriteFailureTest use to force this exact shape.
    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')->andReturn(['primary' => null, 'related' => []]);
    app()->instance(QueryClassifier::class, $classifier);

    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'why though?');

    $texts = collect($received)->where('type', 'content')->pluck('text');
    expect($texts->first(fn ($t) => str_contains($t, "didn't catch that")))->not->toBeNull();

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'] ?? null)->toBeNull();

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});

// ── Task 5: deferred questions raised at both completion terminals ─────────

it('raises deferred questions at the classic completion terminal and clears them', function () {
    [$user, $conversation] = interruptionUser();
    $conversation->update(['metadata' => array_merge($conversation->metadata ?? [], [
        'deferred_questions' => [['question' => 'How healthy are my overall finances?', 'state_id' => 'path_choice']],
    ])]);

    $received = [];
    $method = new ReflectionMethod(OnboardingChatDirector::class, 'emitDoneTurn');
    foreach ($method->invoke(app(OnboardingChatDirector::class), $user, $conversation) as $event) {
        $received[] = $event;
    }

    $raise = collect($received)->where('type', 'quick_replies')
        ->first(fn ($e) => str_contains($e['prompt_text'] ?? '', 'Earlier you asked'));
    expect($raise)->not->toBeNull();
    expect($raise['bubbles'][0]['label'])->toBe('How healthy are my overall finances?');

    // Event-ordering assertion: raise quick_replies must come before celebration content.
    $types = collect($received)->pluck('type');
    $raiseIndex = collect($received)->search(fn ($e) => ($e['type'] ?? null) === 'quick_replies' && str_contains($e['prompt_text'] ?? '', 'Earlier you asked'));
    $celebrationIndex = $types->search('content');
    expect($raiseIndex)->not->toBeFalse();
    expect($celebrationIndex)->not->toBeFalse();
    expect($raiseIndex)->toBeLessThan($celebrationIndex);

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'] ?? null)->toBeNull();

    // The persisted raise message keeps its bubbles for transcript renders.
    $persisted = $conversation->messages()->where('content', 'like', 'Earlier you asked%')->first();
    expect(array_column($persisted->metadata['bubbles'] ?? [], 'label'))
        ->toBe(['How healthy are my overall finances?']);
});

it('raises deferred questions at the campaign completion terminal before the app note and celebration', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign_synthesis',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'metadata' => [
            'source' => 'fyn_onboarding',
            'campaign' => 'pensioncheck',
            'deferred_questions' => [['question' => 'How healthy are my overall finances?', 'state_id' => 'base_work']],
        ],
    ]);

    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));

    // Any non-empty message at campaign_synthesis advances via
    // interpretAnswer (free_text, no parser) → getNextStateId →
    // emitTerminalNavigationTurn — mirrors CampaignReentryExitTest's drive.
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'ok']);

    $response->assertOk();
    $response->streamedContent();

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'] ?? null)->toBeNull();
    // Never clobber the resume-lookup pivot already on the conversation.
    expect($conversation->metadata['source'] ?? null)->toBe('fyn_onboarding');

    $assistantMessages = AiMessage::where('conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->orderBy('id')
        ->get(['content', 'metadata']);

    $raiseIndex = $assistantMessages->search(
        fn (AiMessage $m): bool => str_contains($m->content, 'Earlier you asked')
    );
    $appNoteIndex = $assistantMessages->search(
        fn (AiMessage $m): bool => str_contains($m->content, 'even better in the app')
    );
    $celebrationIndex = $assistantMessages->search(
        fn (AiMessage $m): bool => str_contains($m->content, 'pension picture')
    );

    expect($raiseIndex)->not->toBeFalse();
    expect($appNoteIndex)->not->toBeFalse();
    expect($celebrationIndex)->not->toBeFalse();
    // Ordering contract: deferred raise → app note → celebration/route last.
    expect($raiseIndex)->toBeLessThan($appNoteIndex);
    expect($appNoteIndex)->toBeLessThan($celebrationIndex);

    $raiseMessage = $assistantMessages[$raiseIndex];
    expect(array_column($raiseMessage->metadata['bubbles'] ?? [], 'label'))
        ->toBe(['How healthy are my overall finances?']);
});

// ── Task 6: grouped-extract retries route through interruption intelligence ─

it('answers a question asked at a grouped-extract step instead of retrying blind', function () {
    // STATE_BASE_PERSONAL is a grouped_extract state: handleGroupedExtractTurn
    // makes its OWN LLM call first (the narrow capture_personal_details
    // extraction tool) before any retry is considered. The scripted-turn
    // harness (FynStreamHarness/scriptedFynClientWithText) scripts the shared
    // Anthropic\Client, but that client is consumed by BOTH the extraction
    // call and (if the retry-path interruption check fires) the advice
    // planner+reasoner calls in strict FIFO order — there is no way to target
    // only the second consumer via the raw client queue. Mocking
    // CoordinatingAgent::chatWithPromptOverride directly (the same idiom
    // tests/Feature/Fyn/CaptureTurnAnswersQuestionsTest.php's driveCaptureTurn
    // uses) sidesteps that ordering problem entirely: call #1 is the
    // extraction turn (scripted to decline the tool, forcing the no-capture
    // path into emitRetry), call #2 is the interruption dispatcher's advice
    // reasoner turn. The advice-mode planner needs no scripting at all — the
    // Pest.php global hook already binds an EMPTY ScriptedAnthropicClient for
    // this directory, and Planner::plan() decodes '' to a default `reason`
    // action for free when its queue is empty (see Planner::decode()).
    $calls = 0;
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                // Extraction call: the model calls neither the tool nor
                // emits prose — a clean no-capture turn.
                return (function () {
                    yield ['type' => 'done', 'message_id' => 100];
                })();
            }

            // Interruption dispatcher's advice-mode reasoner turn.
            return (function () {
                yield ['type' => 'content', 'text' => 'You currently spend about £2,400 a month.'];
                yield ['type' => 'done', 'message_id' => 101];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    $received = driveDirector($user, $conversation, 'What do I spend each month?');

    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->not->toContain("didn't catch that")
        ->and($texts)->toContain('You currently spend about £2,400 a month.');

    // Both scripted turns fired — the extraction call AND the interruption's
    // advice call — proving the retry path actually routed through
    // handleInterruption rather than short-circuiting earlier.
    expect($calls)->toBe(2);

    // The walk stayed on the grouped-extract step; nothing advanced blind.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
});

it('suppresses the interruption advice answer when A1 already answered the question this turn', function () {
    // Reviewer-flagged double-answer bug: A1's grouped-extract answer-the-
    // user-first buffer (handleGroupedExtractTurn, ~line 2352) already
    // voices a (deliberately figure-redacted) answer when the extraction
    // model emits prose instead of calling its tool. Before this guard,
    // emitRetry unconditionally called handleInterruption afterwards, which
    // routed the SAME question through the advice-mode reasoner for a
    // SECOND, independently-sourced (and potentially un-redacted) answer.
    // Only ONE scripted LLM call — the extraction turn — should fire here;
    // if the guard regresses, handleInterruption's advice-mode call fires
    // a second time and $calls climbs to 2.
    $calls = 0;
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function () use (&$calls) {
            $calls++;

            // Extraction call: the model emits an A1-style prose answer
            // (no £ figures, so it survives filterOffScriptContent) but
            // calls neither the capture tool nor errors — a clean
            // no-capture turn that falls into the emitRetry path.
            return (function () {
                yield ['type' => 'content', 'text' => 'People typically track spending across housing, food, and transport.'];
                yield ['type' => 'done', 'message_id' => 200];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    $received = driveDirector($user, $conversation, 'What do I spend each month?');

    $contentTexts = collect($received)->where('type', 'content')->pluck('text');

    // Exactly two content events: the A1 answer, then the scripted retry
    // text — never a third, second-sourced advice-loop answer.
    expect($contentTexts)->toHaveCount(2);
    expect($contentTexts->first())->toContain('housing, food, and transport');
    expect($contentTexts->last())->not->toContain('housing, food, and transport')
        ->and($contentTexts->last())->toContain("didn't catch both pieces");

    // Only the extraction call fired — the guard stopped emitRetry from
    // ever invoking handleInterruption's second (advice-mode) LLM call.
    expect($calls)->toBe(1);

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
});
