<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

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
