<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\ExpenditureProfile;
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
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
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

// ── Fix: escape phrase while awaiting_detail — every reply used to be
// treated as the missing detail with no way out ───────────────────────────

it('escapes the awaiting-detail loop when the user says "Forget it" instead of supplying the missing detail', function () {
    [$user, $conversation] = interruptionUser();
    driveDirector($user, $conversation, 'I have £15,000 in a Halifax savings account at 4.1% interest');

    $user->refresh();
    $context = $user->onboarding_fyn_context;
    $context['pending_interruption_store']['awaiting_detail'] = true;
    $user->onboarding_fyn_context = $context;
    $user->save();

    $received = driveDirector($user->refresh(), $conversation, 'Forget it');

    // No capture was attempted — no tool call was emitted for this turn.
    expect(collect($received)->where('type', 'tool_use'))->toBeEmpty();

    $texts = collect($received)->where('type', 'content')->pluck('text');
    expect($texts->first(fn ($t) => str_contains($t, "we'll cover it during setup")))->not->toBeNull();

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);

    // The walk's current step is re-emitted.
    expect(collect($received)->where('type', 'quick_replies')->count())->toBeGreaterThan(0);
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

// ── Fix: a fully-rescued write with no narration before the failing tool
// call must never blank the persisted assistant row to '' (review finding
// M-2). handleInlineCapture computes a "safe prefix" of content events
// preceding the earliest rescued content_offset; when the tool call was the
// very first thing in the turn that offset is 0, so the safe prefix — and
// therefore the text it would write back to the row — is empty. Overwriting
// the row's real content ("Understood.", persisted by the underlying model
// stream) with '' would leave a genuinely blank assistant message in the
// transcript.

it('never blanks the persisted assistant row when a rescued write has no narration before the failing tool call', function () {
    $this->seed(TierConfigurationSeeder::class);
    [$user, $conversation] = interruptionUser();

    // Turn 1: user volunteers a savings account mid-onboarding. Pure
    // classifier path (handleInformationInterruption) — no LLM call.
    driveDirector($user, $conversation, 'I have £15,000 in a Halifax savings account at 4.1% interest');

    // Simulate having already been asked once for ownership — skips the
    // "Yes, save it" acceptance turn (irrelevant to the mechanism under
    // test).
    $user->refresh();
    $context = $user->onboarding_fyn_context;
    $context['pending_interruption_store']['awaiting_detail'] = true;
    $user->onboarding_fyn_context = $context;
    $user->save();

    // Turn 2: a BARE tool call — no preceding narration — is the model's
    // first LLM call this turn, so the failure's content_offset is 0. The
    // model's second LLM call (after seeing the tool error) narrates
    // "Understood." — that is what the underlying stream persists as the
    // row's real content before this deterministic pass runs. The model
    // omits ownership_type again; the deterministic gap-fill rescues it from
    // the user's own wording.
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

    driveDirector($user->refresh(), $conversation, "Yes, it's owned individually by me");

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and($account->ownership_type)->toBe('individual');

    // No assistant row in the transcript was left with blank content — the
    // rescued turn either kept its original (stale but non-empty) narration
    // or never touched the row at all.
    $blankAssistantMessages = $conversation->messages()
        ->where('role', 'assistant')
        ->where('content', '')
        ->count();
    expect($blankAssistantMessages)->toBe(0);
});

// ── Fix: gap-fill evidence override survives an interposed store-offer
// affirmation (live conversation 164 — the exact two-step store-offer
// shape) ─────────────────────────────────────────────────────────────────
//
// Unlike the single-round rescue above (which deliberately skips the "Yes,
// save it" turn — see its own comment), this reproduces the LIVE defect:
// volunteer → offer → "Yes, save it" (a REAL, separately-persisted user
// turn) → clarification → "Just me". CaptureAccuracyGate's DB-derived
// evidence chain-walk (CoordinatingAgent::recentUserMessageEvidence)
// treats the interposed "Yes, save it" segment as non-standalone evidence
// and breaks the chain before it ever reaches "Just me", so even a
// correctly-parsed ownership_type from the deterministic gap-fill used to
// be rejected by the anti-hallucination check and the pending flag
// re-armed forever. The gap-fill now carries an evidence override built
// from its own (verbatim) capture message, bypassing the broken DB
// chain-walk entirely.

it('rescues the gate-blocked write across an interposed "Yes, save it" turn instead of re-arming forever', function () {
    $this->seed(TierConfigurationSeeder::class);
    [$user, $conversation] = interruptionUser();

    // Turn 1: user volunteers a savings account mid-onboarding. Pure
    // classifier path (handleInformationInterruption) — no LLM call.
    driveDirector($user, $conversation, 'I have a Halifax fixed term savings account with £1,500 in it');

    // Turn 2: the user accepts the store offer with a REAL "Yes, save it"
    // message — persisted to the conversation before routing (see
    // OnboardingChatDirector::handleUserMessage's persistUserMessage call)
    // — the exact segment that breaks CaptureAccuracyGate's DB evidence
    // chain-walk once the ownership detail is added below. The model
    // pattern-locks and omits ownership_type on its own attempt, so the
    // write is blocked and the pending flag re-arms awaiting the detail.
    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', [
            'account_name' => 'Halifax Fixed Term Bond',
            'account_type' => 'fixed_term',
            'institution' => 'Halifax',
            'current_balance' => 1500.0,
        ])
        ->textTurn('Understood.')
        ->bind();

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);

    driveDirector($user->refresh(), $conversation, 'Yes, save it');

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store']['awaiting_detail'] ?? null)->toBeTrue();

    // Turn 3: the user answers with explicit individual-ownership wording.
    // The model pattern-locks on its own prior failing tool call and OMITS
    // ownership_type AGAIN (mirroring live conversation 164) — the
    // deterministic gap-fill must rescue it despite the interposed "Yes,
    // save it" turn. Live event shape (msg 19465): the model narrates
    // BEFORE calling the (still-failing) tool — textThenToolTurn, not a
    // bare toolTurn — so handleInlineCapture's content-buffering-until-
    // write-outcome-known path is genuinely exercised. A bare toolTurn
    // (no preceding narration) let the earlier version of this test pass
    // even when a rescued write failed to suppress the failure text.
    FynStreamHarness::fake()
        ->textThenToolTurn("I'll record that for you now.", 'create_savings_account', [
            'account_name' => 'Halifax Fixed Term Bond',
            'account_type' => 'fixed_term',
            'institution' => 'Halifax',
            'current_balance' => 1500.0,
        ])
        ->textTurn("I couldn't save that — I need you to confirm whether you own it individually or with someone else.")
        ->bind();

    $received = driveDirector($user->refresh(), $conversation, 'Just me');

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and($account->institution)->toBe('Halifax')
        ->and((float) $account->current_balance)->toEqual(1500.0)
        ->and($account->ownership_type)->toBe('individual');

    // A rescued write must never surface as "I couldn't save that" — the
    // model's own narration ("I'll record that for you now.") should be
    // the whole of the streamed/persisted content, not that text glued to
    // the deterministic failure message (live conversation 164, msg 19465).
    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->not->toContain("couldn't save");
    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect($persisted->content)->not->toContain("couldn't save");

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

// ── Fix: question-branch nested capture clarification must not be buried
// (review finding I-1). The inline advice turn's tool list keeps
// delegate_to_capture, so a question-phrased write ("Can you add my ISA?")
// can surface a gate clarification as the advice answer itself. Mirrors
// resolvePendingInterruptionCapture's post-capture check: when the latest
// persisted assistant content is clarification-shaped, arm
// pending_interruption_store with awaiting_detail instead of unconditionally
// re-emitting the walk step on top of it.

it('arms the pending store offer instead of burying the clarification when a question-phrased write turns up a gate clarification', function () {
    $this->seed(TierConfigurationSeeder::class);
    $clarification = 'I need to confirm ownership before I can save this — is it individually owned by you?';

    // Realistic handoff simulation (mirrors AdviceFynRoutesWritesViaHandoffTest's
    // fynBindMocks idiom): the advice-mode reasoner call emits the synthetic
    // `handoff` event FynLoop::interceptHandoff consumes, which routes into a
    // SEPARATE data_capture-persona call (handleInlineCapture's own
    // fynLoop->stream) that is what actually narrates the clarification. This
    // is the real mechanics the is_interruption_answer fix distinguishes from
    // a plain advisory answer that merely happens to end in a question — see
    // OnboardingChatDirector::handleQuestionInterruption, which now gates the
    // clarification-arm on persona 'data_capture' rather than on content
    // shape alone. A plain scripted text turn (no handoff, persona 'advice')
    // would no longer arm the store offer under that fix, so this test must
    // exercise the real handoff shape to keep pinning genuine I-1 behaviour.
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
        ) use ($clarification) {
            if ($personaOverride === 'data_capture') {
                return (function () use ($clarification, $conversationArg) {
                    // Mirrors what the real chatWithPromptOverride
                    // implementation persists (see
                    // driveDirectorWithScriptedCaptureTurn's own comment
                    // above) — handleQuestionInterruption's post-turn persona
                    // check inspects a real row, not a mocked event stream.
                    $conversationArg->messages()->create([
                        'role' => 'assistant',
                        'content' => $clarification,
                        'persona' => 'data_capture',
                    ]);
                    yield ['type' => 'content', 'text' => $clarification];
                    yield ['type' => 'done'];
                })();
            }

            return (function () {
                yield ['type' => 'handoff', 'handoff_type' => 'delegate_to_capture', 'payload' => [
                    'reason' => 'user wants to add an ISA',
                    'entity_types' => ['savings_account'],
                    'fields_needed' => ['institution', 'balance'],
                ]];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    app()->instance(CoordinatingAgent::class, $mock);

    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'Can you add my ISA for me?');

    // The clarification is passed straight through as the live question — it
    // must not be buried under a re-emitted walk step.
    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->toContain('individually owned');
    expect(collect($received)->where('type', 'quick_replies'))->toBeEmpty();
    expect(collect($received)->pluck('type')->filter(fn ($t) => $t === 'done'))->toHaveCount(1);

    $user->refresh();
    $pending = $user->onboarding_fyn_context['pending_interruption_store'] ?? null;
    expect($pending)->not->toBeNull();
    expect($pending['awaiting_detail'] ?? null)->toBeTrue();
    expect($pending['message'] ?? null)->toBe('Can you add my ISA for me?');
    expect($pending['state_id'] ?? null)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    // WriteIntentClassifier::classify() short-circuits to null for any
    // message that looks like a question — the same check that routed this
    // message into handleQuestionInterruption in the first place — so the
    // generic fallback shape is armed rather than a real classification.
    expect($pending['intent'] ?? null)->toBe([
        'reason' => 'question_phrased_write',
        'entity_type' => 'savings_account',
        'fields_needed' => [],
    ]);

    // The walk step pointer itself never moved.
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
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
    // CSJ raise shape: the topic is derived deterministically at defer time
    // (classifier primary → module label) so the completion raise can say
    // "You asked about '…' earlier" without re-classifying.
    expect($conversation->metadata['deferred_questions'][0]['topic'] ?? null)
        ->toBe('your overall finances');
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
    // Legacy entry shape (no stored topic) — the raise falls back to quoting
    // the question itself. CSJ raise shape (2026-07-23): thanks by name,
    // names the completed flow, references the question, warns more
    // information may be needed, Yes/No bubbles.
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
        ->first(fn ($e) => str_contains($e['prompt_text'] ?? '', 'You asked about'));
    expect($raise)->not->toBeNull();
    expect($raise['prompt_text'])->toContain('Thanks Chris');
    expect($raise['prompt_text'])->toContain('your onboarding is now complete');
    expect($raise['prompt_text'])->toContain("You asked about 'How healthy are my overall finances?' earlier");
    expect($raise['prompt_text'])->toContain('additional information');
    expect($raise['prompt_text'])->toContain('Are you okay to continue?');
    expect(array_column($raise['bubbles'], 'label'))->toBe(['Yes', 'No']);

    // Event-ordering assertion: raise quick_replies must come before celebration content.
    $types = collect($received)->pluck('type');
    $raiseIndex = collect($received)->search(fn ($e) => ($e['type'] ?? null) === 'quick_replies' && str_contains($e['prompt_text'] ?? '', 'You asked about'));
    $celebrationIndex = $types->search('content');
    expect($raiseIndex)->not->toBeFalse();
    expect($celebrationIndex)->not->toBeFalse();
    expect($raiseIndex)->toBeLessThan($celebrationIndex);

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'] ?? null)->toBeNull();

    // The persisted raise message keeps its bubbles for transcript renders.
    $persisted = $conversation->messages()->where('content', 'like', '%You asked about%')->first();
    expect(array_column($persisted->metadata['bubbles'] ?? [], 'label'))
        ->toBe(['Yes', 'No']);
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
        fn (AiMessage $m): bool => str_contains($m->content, 'You asked about')
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

    // CSJ raise shape: names the completed campaign, Yes/No bubbles.
    $raiseMessage = $assistantMessages[$raiseIndex];
    expect($raiseMessage->content)->toContain('your Pension Check onboarding is now complete');
    expect(array_column($raiseMessage->metadata['bubbles'] ?? [], 'label'))
        ->toBe(['Yes', 'No']);
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
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
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
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
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

it('routes the interruption dispatcher\'s answer through when the A1 prose is only a re-ask, not an answer', function () {
    // Live bug (csjones, /m campaign income step): the extraction model's
    // ONLY prose was a re-ask — "Thanks — I still need your gross annual
    // income in GBP. Could you share that?" — never an answer to the
    // user's actual question ("Am I on track for retirement?"). The
    // pre-fix guard treated ANY non-empty A1 prose as "already answered"
    // and set answerAlreadyVoiced=true, so emitRetry's
    // `! ($answerAlreadyVoiced && isQuestion)` check skipped
    // handleInterruption entirely — the user's real question was deflected
    // and never answered. The fix requires the A1 prose to also fail
    // captureResponseRequestsClarification before it counts as "answered";
    // this re-ask trips that heuristic (it ends in a literal "?" and
    // contains "could you"), so $a1AnswerEmitted stays false and
    // emitRetry proceeds to handleInterruption as normal — mirroring the
    // two-call CoordinatingAgent mock idiom from the "answers a question
    // asked at a grouped-extract step instead of retrying blind" test
    // above (Task 6).
    $calls = 0;
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                // Extraction call: the model emits ONLY a re-ask (no
                // capture tool call, no genuine answer) — the exact live
                // phrasing that exposed the bug.
                return (function () {
                    yield ['type' => 'content', 'text' => 'Thanks — I still need your gross annual income in GBP. Could you share that?'];
                    yield ['type' => 'done', 'message_id' => 400];
                })();
            }

            // Interruption dispatcher's advice-mode reasoner turn — the
            // real answer to the user's actual question, which must fire
            // now that the re-ask no longer counts as "already answered".
            return (function () {
                yield ['type' => 'content', 'text' => 'Based on your current pension and savings, you are broadly on track for retirement.'];
                yield ['type' => 'done', 'message_id' => 401];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    $received = driveDirector($user, $conversation, 'Am I on track for retirement?');

    $contentTexts = collect($received)->where('type', 'content')->pluck('text');

    // The first sentence of the re-ask ("...in GBP.") is itself stripped by
    // filterOffScriptContent's pre-existing personal-figure guard (it
    // matches \bgbp\b) — a filtering behaviour this fix does not touch —
    // leaving "Could you share that?" as A1's own emitted content. The real
    // interruption answer is what matters here: it fires as a SECOND
    // content event, proving the re-ask never suppressed it.
    expect($contentTexts->first())->toContain('Could you share that?');
    expect($contentTexts->implode(' | '))->toContain('broadly on track for retirement');

    // Both scripted turns fired — the extraction call AND the
    // interruption's advice call — proving the guard let handleInterruption
    // run instead of short-circuiting on the re-ask.
    expect($calls)->toBe(2);

    // The walk stayed on the grouped-extract step; nothing advanced blind.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
});

// ── Fix: an interruption ANSWER must not arm the capture-followup path, so
// the state's own deterministic parser still handles the next reply (live
// conversation 168, msg 19558-19563) ───────────────────────────────────────
//
// "Am I on track for retirement?" got a plain advisory answer that happened
// to close with its own offer ("Would you like me to help you add those
// now?"). Before this fix, handleQuestionInterruption's
// captureResponseRequestsClarification check treated that offer as a genuine
// capture clarification and armed pending_interruption_store — so the user's
// NEXT reply, the genuine on-script "14 March 1988" DOB answer, was merged
// into a bogus "Original capture details: Am I on track for retirement? /
// Requested missing details: 14 March 1988" capture-turn message instead of
// reaching base_personal's own extraction tool.

it("tags the interruption dispatcher's plain advisory answer and lets the DOB extraction handle the genuine on-script reply instead of hijacking it into a bogus capture merge", function () {
    $advisoryAnswer = 'To give you personalised guidance on whether you\'re on track for retirement, '
        .'I need your date of birth and pension details. Would you like me to help you add those now?';

    // Partial mock over the REAL CoordinatingAgent instance: chatWithPromptOverride
    // is stubbed for the two LLM-driven calls this scenario needs (the
    // grouped_extract state's own extraction attempt, then the interruption
    // dispatcher's advice-mode reasoner call); every other method — crucially
    // executeTool, which hydrateFromParking calls directly and
    // deterministically for the second user turn below — passes through to
    // the real implementation, so a genuine "14 March 1988" DOB capture is
    // exercised for real rather than re-mocked.
    $calls = 0;
    $mock = Mockery::mock(app(CoordinatingAgent::class));
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function (...$args) use (&$calls, $advisoryAnswer) {
            $calls++;
            /** @var AiConversation $conversationArg */
            $conversationArg = $args[1];
            $personaOverride = $args[8] ?? null;

            if ($calls === 1) {
                // base_personal's own extraction attempt: the model neither
                // captures the DOB nor emits prose — a clean no-capture turn
                // that falls into emitRetry.
                return (function () {
                    yield ['type' => 'done', 'message_id' => 700];
                })();
            }

            // emitRetry -> handleInterruption -> handleQuestionInterruption's
            // advice-mode reasoner call (persona 'advice'). Mirrors the live
            // content exactly (msg 19561) — a plain advisory answer that
            // happens to end in a clarification-shaped offer, with NO
            // delegate_to_capture handoff and no capture tool call at all —
            // mirroring what the real chatWithPromptOverride implementation
            // persists (see driveDirectorWithScriptedCaptureTurn's own
            // comment above) so handleQuestionInterruption's post-turn
            // persona check inspects a real row.
            $conversationArg->messages()->create([
                'role' => 'assistant',
                'content' => $advisoryAnswer,
                'persona' => $personaOverride,
            ]);

            return (function () use ($advisoryAnswer) {
                yield ['type' => 'content', 'text' => $advisoryAnswer];
                yield ['type' => 'done', 'message_id' => 701];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    app()->instance(CoordinatingAgent::class, $mock);

    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    $received = driveDirector($user, $conversation, 'Am I on track for retirement?');

    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' | ');
    expect($texts)->toContain('Would you like me to help you add those now?');

    // The plain advisory answer must NOT arm pending_interruption_store — it
    // is tagged is_interruption_answer instead, and the walk resumed
    // normally on the SAME state (never buried, never advanced blind).
    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);

    $advisoryMessage = $conversation->messages()
        ->where('role', 'assistant')
        ->where('content', $advisoryAnswer)
        ->first();
    expect($advisoryMessage)->not->toBeNull();
    expect($advisoryMessage->metadata['is_interruption_answer'] ?? null)->toBeTrue();

    // The genuine on-script DOB reply must route straight back through the
    // state's own deterministic path (OnboardingFactExtractor parks it,
    // hydrateFromParking commits it via a direct executeTool call — no
    // LLM turn at all) rather than through a merged "Original capture
    // details: ... / Requested missing details: ..." capture turn built from
    // the unrelated interrupting question.
    driveDirector($user->refresh(), $conversation, '14 March 1988');

    // No extra LLM call fired — the deterministic parking hydration handled
    // it, proving the reply never routed through resolvePendingInterruptionCapture
    // / handleInlineCapture's LLM-driven merge path.
    expect($calls)->toBe(2);

    $user->refresh();
    expect($user->date_of_birth?->format('Y-m-d'))->toBe('1988-03-14');
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
});

it('still arms pending_interruption_store for a genuine capture clarification reached via a real delegate_to_capture handoff', function () {
    // Sanity check (task item 2): a REAL capture clarification (untagged,
    // reachable only via a genuine delegate_to_capture handoff — see the
    // "arms the pending store offer..." test above) still routes to the
    // followup/merge path. Re-asserts the same behaviour from a
    // grouped_extract state to confirm the fix is scoped to plain advisory
    // answers, not to clarifications generally.
    $clarification = 'I need to confirm the balance before I can save this — how much is in the account?';

    $mock = Mockery::mock(app(CoordinatingAgent::class));
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function (...$args) use ($clarification) {
            /** @var AiConversation $conversationArg */
            $conversationArg = $args[1];
            $personaOverride = $args[8] ?? null;

            if ($personaOverride === 'data_capture') {
                return (function () use ($clarification, $conversationArg) {
                    $conversationArg->messages()->create([
                        'role' => 'assistant',
                        'content' => $clarification,
                        'persona' => 'data_capture',
                    ]);
                    yield ['type' => 'content', 'text' => $clarification];
                    yield ['type' => 'done'];
                })();
            }

            if ($personaOverride === 'advice') {
                return (function () {
                    yield ['type' => 'handoff', 'handoff_type' => 'delegate_to_capture', 'payload' => [
                        'reason' => 'user wants to add a savings account',
                        'entity_types' => ['savings_account'],
                        'fields_needed' => ['balance'],
                    ]];
                })();
            }

            // base_personal's own extraction attempt: no capture, no prose.
            return (function () {
                yield ['type' => 'done', 'message_id' => 800];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    app()->instance(CoordinatingAgent::class, $mock);

    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    driveDirector($user, $conversation, 'Can you add my savings account for me?');

    $user->refresh();
    $pending = $user->onboarding_fyn_context['pending_interruption_store'] ?? null;
    expect($pending)->not->toBeNull();
    expect($pending['awaiting_detail'] ?? null)->toBeTrue();
});

it('discards a poisoned awaiting-detail payload whose original is a question', function () {
    // Live conversation 168: flags armed before the persona-gate fix stored a
    // QUESTION as the pending capture message. On read, such payloads must be
    // dropped so the user's next on-script answer reaches the state's normal
    // handling instead of a bogus capture merge.
    [$user, $conversation] = interruptionUser();
    $context = ['pending_interruption_store' => [
        'message' => 'Original capture details: Am I on track for retirement?'
            ."\nRequested missing details: 14 March 1988",
        'intent' => ['reason' => 'question_phrased_write', 'entity_type' => 'savings_account', 'fields_needed' => []],
        'state_id' => OnboardingStateMachine::STATE_PATH_CHOICE,
        'awaiting_detail' => true,
    ]];
    $user->onboarding_fyn_context = $context;
    $user->save();

    $received = driveDirector($user, $conversation, 'Follow a journey');

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
    // The bubble answer reached path_choice's own interpretation and advanced
    // the walk — no capture merge hijacked it.
    expect($user->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->not->toContain('I can only help with');
});

// ── Fix: a volunteered record must never be swallowed by an amount/date
// parser at a free_text state (live bug, csjones /m campaign expenditure
// step). parseExpenditureAmount and parseIncomeAmount both extract ANY
// embedded £-figure, so "I have a Halifax fixed term savings account with
// £1,500 in it" parsed successfully (£1,500) and was recorded as monthly
// spending — the volunteered savings account was never offered. The
// interruption dispatcher only ever ran on interpretation FAILURE, so a
// successful-but-wrong parse never reached it. Site A now classifies BEFORE
// accepting a parser result on free_text states; a non-null write intent
// routes through the same store-offer path as an unparseable answer. ─────────

it('does not record a volunteered savings account as expenditure and offers to store it instead', function () {
    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $received = driveDirector($user, $conversation, 'I have a Halifax fixed term savings account with £1,500 in it');

    $offer = collect($received)->firstWhere('type', 'quick_replies');
    expect($offer)->not->toBeNull();
    expect($offer['prompt_text'])->toContain('save');
    expect(array_column($offer['bubbles'], 'id'))->toBe(['store_now', 'store_later']);

    // No capture_complete receipt — expenditure was never touched.
    expect(collect($received)->where('type', 'capture_complete'))->toHaveCount(0);

    $user->refresh();
    expect($user->monthly_expenditure)->toBeNull();
    expect(ExpenditureProfile::where('user_id', $user->id)->exists())->toBeFalse();
    // The walk did not advance — it's still parked at expenditure.
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $pending = $user->onboarding_fyn_context['pending_interruption_store'] ?? null;
    expect($pending)->not->toBeNull();
    expect($pending['message'])->toBe('I have a Halifax fixed term savings account with £1,500 in it');
    expect($pending['intent']['entity_type'] ?? null)->toBe('savings_account');
});

it('does not record a volunteered pension as expenditure and offers to store it instead', function () {
    // Same class of bug as above, exercised with a different entity type
    // (pension, as in the live bug report's income-step description) at the
    // same real free_text/value_parser state — the codebase has no dedicated
    // free-text income-amount capture state (income is captured via the
    // grouped_extract base_work step, which has its own separate capture
    // handling and is out of scope here); base_expenditure is the only real
    // state that greedily parses "any embedded number" the way an income
    // amount parser would.
    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $received = driveDirector($user, $conversation, 'I have a pension worth £40,000');

    $offer = collect($received)->firstWhere('type', 'quick_replies');
    expect($offer)->not->toBeNull();
    expect(array_column($offer['bubbles'], 'id'))->toBe(['store_now', 'store_later']);

    expect(collect($received)->where('type', 'capture_complete'))->toHaveCount(0);

    $user->refresh();
    expect($user->monthly_expenditure)->toBeNull();
    expect(ExpenditureProfile::where('user_id', $user->id)->exists())->toBeFalse();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $pending = $user->onboarding_fyn_context['pending_interruption_store'] ?? null;
    expect($pending)->not->toBeNull();
    expect($pending['intent']['entity_type'] ?? null)->toBe('pension');
});

it('still records a genuine plain-amount expenditure answer and advances', function () {
    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $received = driveDirector($user, $conversation, 'About £1,800 a month');

    // No store-offer bubbles — advancing to profile_review_expenditure emits
    // its OWN quick_replies (its "Looks correct" confirm bubble), which is
    // expected; only the store_now/store_later shape would indicate the
    // guard misfired.
    $offer = collect($received)->firstWhere('type', 'quick_replies');
    if ($offer !== null) {
        expect(array_column($offer['bubbles'], 'id'))->not->toBe(['store_now', 'store_later']);
    }
    $receipt = collect($received)->firstWhere('type', 'capture_complete');
    expect($receipt)->not->toBeNull();
    expect($receipt['summary'])->toContain('1,800');

    $user->refresh();
    expect((float) $user->monthly_expenditure)->toBe(1800.0);
    expect((float) ExpenditureProfile::where('user_id', $user->id)->value('total_monthly_expenditure'))
        ->toBe(1800.0);
    expect($user->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
});

it('still records a genuine "roughly Nk" expenditure answer and advances', function () {
    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $received = driveDirector($user, $conversation, 'roughly 2k');

    $offer = collect($received)->firstWhere('type', 'quick_replies');
    if ($offer !== null) {
        expect(array_column($offer['bubbles'], 'id'))->not->toBe(['store_now', 'store_later']);
    }
    expect(collect($received)->firstWhere('type', 'capture_complete'))->not->toBeNull();

    $user->refresh();
    expect((float) $user->monthly_expenditure)->toBe(2000.0);
});

// ── Task 2 (structured turn intent): the followup/merge scans prefer the
// enum. A row stamped turn_intent=interruption_answer — with NO legacy
// is_interruption_answer boolean — must be skipped by
// mergeUnresolvedCaptureMessage's scan exactly as the tagged rows are, so
// the genuine on-script reply reaches the state's own parser unmerged (the
// conv-168 shape pinned via the enum rather than the tag). ────────────────

it('skips an enum-stamped interruption answer in the merge scan without the legacy tag', function () {
    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    // Previous user turn — a statement, not a question, so the
    // userAskedQuestion early-return cannot mask the scan.
    $conversation->messages()->create(['role' => 'user', 'content' => 'I was born in March 1988.']);

    // The advisory answer row carries ONLY the enum (future rows stop
    // writing the boolean), with question-shaped wording the legacy
    // heuristic mistakes for a capture clarification.
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I need your date of birth and pension details. Would you like me to help you add those now?',
        'metadata' => ['turn_intent' => 'interruption_answer'],
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => '14 March 1988']);

    $method = new ReflectionMethod(OnboardingChatDirector::class, 'mergeUnresolvedCaptureMessage');
    $merged = $method->invoke(app(OnboardingChatDirector::class), $conversation, '14 March 1988');

    expect($merged)->toBe('14 March 1988');
});

it('merges on an enum-stamped capture clarification even when the wording defeats the legacy heuristic', function () {
    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    $conversation->messages()->create(['role' => 'user', 'content' => 'I have a savings account with Halifax.']);

    // The stamp says clarification; the terse wording carries none of the
    // legacy heuristic's cues. The enum must win.
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'One detail is missing before that saves.',
        'metadata' => ['turn_intent' => 'capture_clarification'],
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'It holds £12,000.']);

    $method = new ReflectionMethod(OnboardingChatDirector::class, 'mergeUnresolvedCaptureMessage');
    $merged = $method->invoke(app(OnboardingChatDirector::class), $conversation, 'It holds £12,000.');

    expect($merged)->toBe(
        "Original capture details: I have a savings account with Halifax.\n"
        .'Requested missing details: It holds £12,000.'
    );
});

// ── Task 4 (structured turn intent): confirmed facts make the two-step
// store flow deterministic — the awaiting-detail reply's extractor parse
// satisfies the gate directly, with no evidence-window dependency. ─────────

it('completes the two-step store flow via confirmed facts when the detail phrasing defeats the gate regexes', function () {
    $this->seed(TierConfigurationSeeder::class);
    [$user, $conversation] = interruptionUser();

    driveDirector($user, $conversation, 'I have a Santander savings account with £9,000 in it');

    $clarification = 'I need you to confirm whether you own it individually or with someone else.';
    $calls = 0;
    $mock = Mockery::mock(app(CoordinatingAgent::class));
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function (...$args) use (&$calls, $clarification) {
            $calls++;
            /** @var AiConversation $conversationArg */
            $conversationArg = $args[1];

            if ($calls === 1) {
                // The "Yes, save it" capture turn: gate-blocked — the model
                // voices the ownership ask. Persisted stamped exactly as the
                // real pipeline (+ the Task 1 refinement) stamps it.
                $conversationArg->messages()->create([
                    'role' => 'assistant',
                    'content' => $clarification,
                    'persona' => 'data_capture',
                    'metadata' => ['turn_intent' => 'capture_clarification'],
                ]);

                return (function () use ($clarification) {
                    yield ['type' => 'content', 'text' => $clarification];
                    yield ['type' => 'done', 'message_id' => 800];
                })();
            }

            // The merged detail turn: the model captures nothing — the
            // deterministic gap-fill (REAL executeTool via the partial
            // mock's passthrough) must land the write.
            return (function () {
                yield ['type' => 'done', 'message_id' => 801];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    // NO setConfirmedCaptureFacts stub here on purpose: this is a PARTIAL
    // mock, and stubbing the setter would swallow the call instead of
    // forwarding it to the real instance — severing the very facts channel
    // this test pins (the real executeTool must read the transient).
    app()->instance(CoordinatingAgent::class, $mock);

    driveDirector($user->refresh(), $conversation, 'Yes, save it');

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store']['awaiting_detail'] ?? null)->toBeTrue();

    // "It's only mine." parses deterministically (extractor's "only mine"
    // pattern) but is NOT in the gate's text-evidence vocabulary (even after
    // the Task 5 phrasing additions) — only the confirmed-facts channel can
    // satisfy the gate for this phrasing.
    driveDirector($user->refresh(), $conversation, "It's only mine.");

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull();
    expect($account->ownership_type)->toBe('individual');

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
});

// ── CSJ decided policy (reconfirmed 2026-07-23): no question is ever
// dropped. At a delegated step, when the model's capture turn only re-asks
// the scripted question (the model-requested-clarification branch), the
// user's question must still route through the interruption dispatcher —
// answered inline when simple, acknowledged + deferred when it needs more.
// Live shape: Tessa, base_work, "Does my gross income include my employer
// pension contributions?" answered with only "I still need your gross
// annual income" — the question vanished. ─────────────────────────────────

it('answers a question inline when the delegated capture turn only re-asks', function () {
    $reAsk = 'Thanks — I still need your gross annual income in GBP. Could you share that?';
    $answer = 'Employer pension contributions are not counted in your gross annual income — quote your salary before pension deductions.';

    $calls = 0;
    $mock = Mockery::mock(app(CoordinatingAgent::class));
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function (...$args) use (&$calls, $reAsk, $answer) {
            $calls++;
            /** @var AiConversation $conversationArg */
            $conversationArg = $args[1];

            if ($calls === 1) {
                // The grouped extraction turn: the tool ran but reported the
                // income missing (the user asked a question instead of
                // giving a figure) — the exact partial-capture shape from
                // the live failure (metadata is_partial_retry +
                // missing_fields [annual_income] on row 19602).
                $conversationArg->messages()->create([
                    'role' => 'assistant',
                    'content' => $reAsk,
                    'persona' => 'data_capture',
                ]);

                return (function () use ($reAsk) {
                    yield ['type' => 'content', 'text' => $reAsk];
                    yield [
                        'type' => 'onboarding_field_captured',
                        'details' => ['missing' => ['annual_income']],
                    ];
                    yield ['type' => 'done', 'message_id' => 900];
                })();
            }

            // The interruption dispatcher's advice-mode answer.
            $conversationArg->messages()->create([
                'role' => 'assistant',
                'content' => $answer,
                'persona' => 'advice',
            ]);

            return (function () use ($answer) {
                yield ['type' => 'content', 'text' => $answer];
                yield ['type' => 'done', 'message_id' => 901];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    app()->instance(CoordinatingAgent::class, $mock);

    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_WORK);

    $received = driveDirector($user, $conversation, 'Does my gross income include my employer pension contributions?');

    // The question was answered, not dropped.
    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' | ');
    expect($texts)->toContain('not counted in your gross annual income');

    // The answer row is tagged so the followup/merge scans never mistake
    // its wording for a capture clarification.
    $answerRow = $conversation->messages()->where('content', $answer)->latest('id')->first();
    expect($answerRow)->not->toBeNull();
    expect($answerRow->metadata['turn_intent'] ?? null)->toBe('interruption_answer');

    // The walk stays parked on the same step for the user's next reply.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK);
});
