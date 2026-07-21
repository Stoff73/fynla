<?php

declare(strict_types=1);

use Anthropic\Client;
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
