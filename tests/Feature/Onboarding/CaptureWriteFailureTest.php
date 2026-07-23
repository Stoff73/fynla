<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * WP-1 — a FAILED write must not masquerade as a capture. The 2026-07-03
 * "Beta Ltd Workplace Pension" was dispatched, failed validation, and the
 * user heard a confident "Recorded…" while nothing persisted. The delegated
 * chat now emits capture_write_result (landed true/false); the director
 * counts only landed writes and, when every attempt failed silently, names
 * what could not be saved.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

function captureFailureUser(): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
        'onboarding_fyn_selection' => 'savings',
        'onboarding_fyn_context' => ['visited_focuses' => ['savings']],
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    return [$user, $conversation];
}

function mockDelegatedStream(array $events): void
{
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function (User $user, AiConversation $conversation) use ($events) {
            $content = '';
            foreach ($events as $event) {
                if (($event['type'] ?? null) === 'content') {
                    $content .= (string) ($event['text'] ?? '');
                }
                if (($event['type'] ?? null) === 'done' && $content !== '') {
                    $message = $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $content,
                    ]);
                    $event['message_id'] = $message->id;
                }
                yield $event;
            }
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    $mock->shouldReceive('setVerifyEditScope')->zeroOrMoreTimes();

    test()->instance(CoordinatingAgent::class, $mock);
}

function verifyEditFailureUser(string $section = 'income'): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => 'campaign_verify_edit',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_context' => ['verify_section' => $section],
        'annual_employment_income' => 75000,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    return [$user, $conversation];
}

it('matches the bare word "rate" to interest_rate in the verify-edit field vocabulary', function () {
    // Live 2026-07-23: "The Santander rate is 4%" matched no field phrase
    // ('interest rate'/'interest percentage' only), so the edit scope came
    // out empty, update_record was filtered from the toolset, and the turn
    // fell to the generic "I wasn't able to apply that change" line.
    $method = new ReflectionMethod(OnboardingChatDirector::class, 'verifyEditRecordFields');

    $fields = $method->invoke(
        app(OnboardingChatDirector::class),
        'savings_account',
        'The Santander rate is 4% and the Halifax rate is 3.5%.'
    );

    expect($fields)->toContain('interest_rate');
});

it('scopes a verify edit to BOTH records when the user names both accounts', function () {
    // Live 2026-07-23: "The Santander rate is 4% and the Halifax rate is
    // 3.5%" matched two records, the exactly-one rule emptied the scope,
    // update_record was filtered from the toolset, and the turn fell to the
    // generic failure line. Explicitly named records are unambiguous.
    $user = User::factory()->create(['is_preview_user' => false]);
    $santander = SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_name' => 'Santander Savings Account', 'institution' => 'Santander',
    ]);
    $halifax = SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_name' => 'Halifax savings account', 'institution' => 'Halifax',
    ]);

    $method = new ReflectionMethod(OnboardingChatDirector::class, 'verifyEditScope');
    $scope = $method->invoke(
        app(OnboardingChatDirector::class),
        $user,
        'savings',
        'the santander rate is 4% and the halifax rate is 3.5%.'
    );

    expect($scope['tools'])->toContain('update_record')
        ->and($scope['records']['savings_account'] ?? [])->toContain($santander->id)
        ->and($scope['records']['savings_account'] ?? [])->toContain($halifax->id)
        ->and($scope['record_fields']['savings_account'] ?? [])->toContain('interest_rate');
});

it('names what could not be saved when every write failed and the model said nothing', function () {
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_pension', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'create_pension', 'landed' => false, 'message' => 'Validation failed for pension.'],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'I pay into a workplace pension at 5 percent'
    ) as $event) {
        $received[] = $event;
    }

    $failureText = collect($received)
        ->filter(fn (array $e) => ($e['type'] ?? null) === 'content')
        ->pluck('text')
        ->first(fn (string $t) => str_contains($t, "couldn't save"));

    expect($failureText)->not->toBeNull()
        ->and($failureText)->toContain('Validation failed for pension');

    // The failure explanation persists so the transcript matches on reload.
    expect($conversation->messages()->where('metadata->capture_write_failed', true)->exists())->toBeTrue()
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
});

it('gap-fills the workplace pension when the model refuses — the answer is never lost', function () {
    // The original live failure end-to-end (2026-07-23): grok voiced the
    // injection refusal on "It's not salary sacrifice", captured nothing,
    // and the contribution answer was silently lost. The occupational
    // capture_focus now engages the ONE deterministic gap-fill, which
    // parses the answer and writes the pension itself.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'annual_employment_income' => 78000,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME,
        'onboarding_fyn_selection' => 'savetax',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            return (function () {
                yield ['type' => 'content', 'text' => 'I can only help with financial planning questions. How can I assist with your finances?'];
                yield ['type' => 'done', 'message_id' => 99];
            })();
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    $mock->shouldReceive('setVerifyEditScope')->zeroOrMoreTimes();
    $capturedInput = null;
    $mock->shouldReceive('executeTool')
        ->once()
        ->andReturnUsing(function (string $tool, array $input) use (&$capturedInput) {
            $capturedInput = ['tool' => $tool, 'input' => $input];

            return [
                'success' => true,
                'entity_type' => 'dc_pension',
                'entity_id' => 4242,
                'message' => 'Created dc pension successfully.',
            ];
        });
    test()->instance(CoordinatingAgent::class, $mock);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        "I contribute 5% and my employer matches it. It's not salary sacrifice."
    ) as $event) {
        $received[] = $event;
    }

    // The gap-fill called create_pension with the parsed answer + salary.
    expect($capturedInput['tool'] ?? null)->toBe('create_pension');
    expect($capturedInput['input']['employee_contribution_percent'] ?? null)->toBe(5.0);
    expect($capturedInput['input']['employer_contribution_percent'] ?? null)->toBe(5.0);
    expect($capturedInput['input']['salary_sacrifice'] ?? null)->toBeFalse();
    expect($capturedInput['input']['annual_salary'] ?? null)->toBe(78000.0);

    // The landed record surfaced and the walk advanced — no re-ask, no
    // refusal text, no silent loss.
    $texts = collect($received)->filter(fn (array $e) => ($e['type'] ?? null) === 'content')->pluck('text')->implode(' | ');
    expect($texts)->not->toContain('only help with financial planning')
        ->and($texts)->not->toContain("didn't catch that");
    expect(collect($received)->contains(fn (array $e) => ($e['type'] ?? null) === 'entity_created' && ($e['entity_id'] ?? null) === 4242))->toBeTrue();
    expect($user->fresh()->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
});

it('never advances a zero-output turn — the refused pension answer re-asks instead of claiming saved', function () {
    // Live 2026-07-23 (workplace-pension step): grok voiced the scripted
    // injection refusal, the system-copy strip emptied it, and the turn —
    // zero tools, zero visible reply — still advanced with "I've saved your
    // pensions" while nothing was written anywhere. A turn that produced
    // NOTHING must re-ask, never advance.
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'content', 'text' => 'I can only help with financial planning questions. How can I assist with your finances?'],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'I contribute 5% and my employer matches it. It is not salary sacrifice.'
    ) as $event) {
        $received[] = $event;
    }

    $texts = collect($received)->filter(fn (array $e) => ($e['type'] ?? null) === 'content')->pluck('text')->implode(' | ');

    // The refusal never reaches the user, nothing claims "saved", and the
    // walk stays parked on the same step with a re-ask.
    expect($texts)->not->toContain('only help with financial planning')
        ->and($texts)->not->toContain('saved')
        ->and($texts)->not->toBe('');
    expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
});

it('shows a tier-limit message verbatim — no missing-detail tail a plan limit cannot satisfy', function () {
    // Live /m msg 19716 (2026-07-23): the delegated composer appended
    // "Give me the missing detail and I will try again." to a plan-limit
    // message. Tier limits carry their own upgrade call to action and show
    // verbatim via the ONE captureFailureText composer (Rule 20).
    [$user, $conversation] = captureFailureUser();

    $tierMessage = "You've reached your plan's limit of 2 savings accounts. To add more, upgrade your plan.";
    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        [
            'type' => 'capture_write_result',
            'tool' => 'create_savings_account',
            'landed' => false,
            'message' => $tierMessage,
            'result' => ['error' => true, 'error_type' => 'tier_limit_reached', 'message' => $tierMessage],
        ],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'I have a Barclays current account with £3,200 in it, owned just by me'
    ) as $event) {
        $received[] = $event;
    }

    $texts = collect($received)->filter(fn (array $e) => ($e['type'] ?? null) === 'content')->pluck('text');
    $tierText = $texts->first(fn (string $t) => str_contains($t, "plan's limit"));
    expect($tierText)->toBe($tierMessage)
        ->and($texts->implode(' '))->not->toContain('Give me the missing detail');
});

it('strips model-echoed failure copy so retries never stack the previous turn\'s failure lines', function () {
    // Live msgs 19708→19712: the model parroted the prior turn's scripted
    // "I couldn't save that — …" lines, the echo tripped the clarification
    // heuristics, and each retry persisted the old failure text above the
    // new one. Echoed failure copy is stripped; the deterministic composer
    // owns the single failure line.
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'content', 'text' => "I couldn't save that — I need you to confirm whether you own it individually or with someone else. Give me the missing detail and I will try again. "],
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'landed' => false, 'message' => 'Validation failed for savings account.'],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'I have a Halifax saver with £2,000 in it'
    ) as $event) {
        $received[] = $event;
    }

    $texts = collect($received)->filter(fn (array $e) => ($e['type'] ?? null) === 'content')->pluck('text');
    $failureLines = $texts->filter(fn (string $t) => str_contains($t, "couldn't save"));

    // Exactly ONE failure line — the composer's, for the real error — and
    // the parroted ownership line is gone.
    expect($failureLines)->toHaveCount(1)
        ->and($failureLines->first())->toContain('Validation failed for savings account')
        ->and($texts->implode(' '))->not->toContain('confirm whether you own it individually');
});

it('holds a question turn whose only write failed instead of advancing', function () {
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'landed' => false, 'message' => 'Validation failed for savings account.'],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Add my Halifax ISA — also, what is a Personal Savings Allowance?'
    ) as $event) {
        $received[] = $event;
    }

    // A failed write is NOT a capture: the question turn stays on the
    // capture state (before WP-1 the raw tool attempt counted and advanced).
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
});

it('holds a campaign ISA turn when Fyn asks for missing capture facts without calling a tool', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => [
            'campaign' => 'savetax',
            'assets' => ['isa'],
        ],
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    mockDelegatedStream([
        [
            'type' => 'content',
            'text' => 'I need the ISA type and whether it is owned individually.',
        ],
        ['type' => 'done', 'message_id' => 100],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'My Barclays ISA has £20,000 and I added £5,000 this tax year.'
    ) as $event) {
        $received[] = $event;
    }

    $content = collect($received)
        ->where('type', 'content')
        ->pluck('text')
        ->implode("\n");

    expect($user->fresh()->onboarding_fyn_step)
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS)
        ->and($content)->toContain('I need the ISA type')
        ->and($content)->not->toContain("I've saved your ISA accounts");
});

it('combines an unresolved ISA answer with its requested missing facts after resume', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => [
            'campaign' => 'savetax',
            'assets' => ['isa'],
        ],
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);
    $conversation->messages()->create([
        'role' => 'user',
        'content' => 'My Barclays ISA has £20,000 and I added £5,000 this tax year.',
    ]);
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I need the ISA type and whether it is owned individually.',
        'metadata' => [
            'onboarding_step' => OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS,
            'capture_write_failed' => true,
        ],
    ]);
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'Welcome back. Would you like to continue?',
        'metadata' => ['is_resume_greeting' => true],
    ]);

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->withArgs(fn (
            User $streamUser,
            AiConversation $streamConversation,
            string $streamMessage
        ): bool => $streamUser->is($user)
            && $streamConversation->is($conversation)
            && str_contains($streamMessage, 'My Barclays ISA has £20,000')
            && str_contains($streamMessage, 'It is a Cash ISA and I own it individually.'))
        ->andReturnUsing(function () {
            yield ['type' => 'done', 'message_id' => 101];
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'It is a Cash ISA and I own it individually.'
    ));
});

it('does not ack "Recorded" when the only write was a blocked duplicate (D1 round 4)', function () {
    // user 168 SIPP turn: the model narrated "Recorded — £200 monthly" but its
    // create_pension was a blocked duplicate (warning, existing_id) — nothing
    // landed, no message. The confident ack must never reach the user.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS,
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding',
    ]);

    mockDelegatedStream([
        ['type' => 'content', 'text' => 'Recorded — £200 monthly into Personal Pension.'],
        ['type' => 'tool_use', 'tool' => 'create_pension', 'status' => 'running'],
        // Deduped create: not an error, so HasAiChat emits landed=false, message=null.
        ['type' => 'capture_write_result', 'tool' => 'create_pension', 'landed' => false, 'message' => null],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, 'About £200 a month into the personal pension'
    ) as $event) {
        $received[] = $event;
    }

    $contentTexts = collect($received)
        ->filter(fn (array $e) => ($e['type'] ?? null) === 'content')
        ->pluck('text');

    // The false success ack must be gone.
    expect($contentTexts->contains(fn (string $t) => str_contains($t, 'Recorded')))->toBeFalse();
    // No "Recorded …" success ack persisted either.
    expect($conversation->messages()->where('content', 'like', '%Recorded — £200%')->exists())->toBeFalse();
});

it('asks only for the missing fact when a campaign account write needs clarification', function (string $modelText) {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'onboarding_fyn_selection' => 'savetax',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    mockDelegatedStream([
        [
            'type' => 'content',
            'text' => $modelText,
        ],
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        [
            'type' => 'capture_write_result',
            'tool' => 'create_savings_account',
            'landed' => false,
            'message' => 'I need explicit ownership for each account.',
        ],
        ['type' => 'done', 'message_id' => 102],
    ]);

    $received = iterator_to_array(
        app(OnboardingChatDirector::class)->handleUserMessage(
            $user,
            $conversation,
            'My Halifax account has £3,500 and my Marcus account has £12,000.'
        ),
        false,
    );
    $content = collect($received)->where('type', 'content')->pluck('text')->implode("\n");
    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->firstOrFail();
    $done = collect($received)->last(fn (array $event): bool => ($event['type'] ?? null) === 'done');

    expect($content)->toBe('I need you to confirm whether you own them individually or jointly.')
        ->and($content)->not->toContain("what's the balance and interest rate")
        ->and(collect($received)->where('type', 'done'))->toHaveCount(1)
        ->and($persisted->content)->toBe('I need you to confirm whether you own them individually or jointly.')
        ->and($persisted->metadata['capture_write_failed'] ?? false)->toBeTrue()
        ->and($done['message_id'] ?? null)->toBe($persisted->id)
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);
})->with([
    'recorded' => 'Recorded — two savings accounts. I need you to confirm whether you own them individually or jointly.',
    'great saved' => "Great — I've saved those. I need you to confirm whether you own them individually or jointly.",
    'thanks recorded' => "Thanks — I've recorded both. I need you to confirm whether you own them individually or jointly.",
    'okay accounts saved' => 'Okay, both accounts are now saved. I need you to confirm whether you own them individually or jointly.',
]);

it('still advances a question turn whose write landed', function () {
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 42, 'name' => 'Halifax ISA'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'landed' => true, 'message' => null],
        ['type' => 'tool_success', 'tool' => 'create_savings_account', 'summary' => 'Halifax ISA added'],
        ['type' => 'content', 'text' => 'Recorded — Halifax ISA.'],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Add my Halifax ISA — also, what is a Personal Savings Allowance?'
    ) as $event) {
        $received[] = $event;
    }

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ADD_MORE);

    // The landed/failed signal itself never reaches the frontend.
    expect(collect($received)->firstWhere('type', 'capture_write_result'))->toBeNull()
        ->and(collect($received)->where('type', 'capture_complete'))->toHaveCount(1)
        ->and(collect($received)->firstWhere('type', 'capture_complete')['records_created'])->toBe([
            ['type' => 'savings_account', 'id' => 42, 'name' => 'Halifax ISA'],
        ]);
});

it('does not advance a multi-record capture while one write is still unresolved', function () {
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 42, 'name' => 'Halifax'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'tool_call_id' => 'landed', 'landed' => true, 'message' => null],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'tool_call_id' => 'failed', 'landed' => false, 'message' => 'Marcus ownership is missing.'],
        ['type' => 'content', 'text' => 'Recorded — both savings accounts.'],
        ['type' => 'done'],
    ]);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Halifax is mine and Marcus has £12,000.'
    ), false);
    $content = collect($events)->where('type', 'content')->pluck('text')->implode("\n");

    expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE)
        ->and($content)->not->toContain('Recorded — both')
        ->and($content)->toContain('Marcus ownership is missing')
        ->and(collect($events)->firstWhere('type', 'capture_write_result'))->toBeNull();
});

it('advances when a failed write is corrected and lands within the same turn', function () {
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'tool_call_id' => 'first', 'landed' => false, 'message' => 'Ownership is missing.'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'tool_call_id' => 'retry', 'retry_of_tool_call_id' => 'first', 'landed' => true, 'message' => null],
        ['type' => 'content', 'text' => 'Recorded — Halifax savings account.'],
        ['type' => 'done'],
    ]);

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'My Halifax account is owned individually.'
    ), false);

    expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ADD_MORE);
});

it('does not strand a partial retry when an already-landed record is a duplicate no-op', function () {
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'tool_call_id' => 'failed-marcus', 'landed' => false, 'message' => 'Marcus ownership is missing.'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'tool_call_id' => 'already-landed', 'landed' => false, 'noop' => true, 'message' => null],
        ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 43, 'name' => 'Marcus'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'tool_call_id' => 'corrected', 'retry_of_tool_call_id' => 'failed-marcus', 'landed' => true, 'message' => null],
        ['type' => 'content', 'text' => 'Recorded — Marcus savings account.'],
        ['type' => 'done'],
    ]);

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Halifax was already saved; Marcus is individually owned with £12,000.'
    ), false);

    expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ADD_MORE);
});

it('keeps a verify edit parked when the attempted update did not land', function () {
    [$user, $conversation] = verifyEditFailureUser();

    mockDelegatedStream([
        ['type' => 'content', 'text' => 'Updated your salary to £82,000.'],
        ['type' => 'tool_use', 'tool' => 'update_profile', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'update_profile', 'landed' => false, 'message' => 'The income update failed validation.'],
        ['type' => 'tool_use', 'tool' => 'update_profile', 'status' => 'complete'],
        ['type' => 'done'],
    ]);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Change my salary to £82,000.'
    ), false);
    $content = collect($events)->where('type', 'content')->pluck('text')->implode("\n");
    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->firstOrFail();
    $done = collect($events)->last(fn (array $event): bool => ($event['type'] ?? null) === 'done');

    expect($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_edit')
        ->and($content)->not->toContain('Updated your salary')
        ->and($content)->toContain('income update failed validation')
        ->and(collect($events)->firstWhere('type', 'capture_write_result'))->toBeNull()
        ->and($persisted->content)->toBe($content)
        ->and($persisted->metadata['verify_edit_failed'] ?? false)->toBeTrue()
        ->and($done['message_id'] ?? null)->toBe($persisted->id);
});

it('keeps a multi-record verify edit parked while any update is unresolved', function () {
    [$user, $conversation] = verifyEditFailureUser('savings');

    mockDelegatedStream([
        ['type' => 'content', 'text' => 'Updated both savings accounts.'],
        ['type' => 'capture_write_result', 'tool' => 'update_record', 'tool_call_id' => 'landed', 'landed' => true, 'message' => null],
        ['type' => 'capture_write_result', 'tool' => 'update_record', 'tool_call_id' => 'failed', 'landed' => false, 'message' => 'The second account update failed.'],
        ['type' => 'done'],
    ]);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Change both account balances.'
    ), false);
    $content = collect($events)->where('type', 'content')->pluck('text')->implode("\n");

    expect($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_edit')
        ->and($content)->not->toContain('Updated both')
        ->and($content)->toContain('second account update failed');
});

it('preserves a verify edit clarification without replacing it with a generic failure', function () {
    [$user, $conversation] = verifyEditFailureUser();

    mockDelegatedStream([
        ['type' => 'content', 'text' => 'Is £82,000 your employment income or your total income from all sources?'],
        ['type' => 'done'],
    ]);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Change it to £82,000.'
    ), false);
    $content = collect($events)->where('type', 'content')->pluck('text')->implode("\n");
    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->firstOrFail();

    expect($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_edit')
        ->and($content)->toBe('Is £82,000 your employment income or your total income from all sources?')
        ->and($content)->not->toContain("wasn't able")
        ->and($persisted->content)->toBe($content)
        ->and($persisted->metadata['verify_edit_clarification'] ?? false)->toBeTrue();
});

it('returns to the same section review only when a verify edit landed', function () {
    [$user, $conversation] = verifyEditFailureUser();

    mockDelegatedStream([
        ['type' => 'content', 'text' => 'Updated your employment income to £82,000.'],
        ['type' => 'tool_use', 'tool' => 'update_profile', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'update_profile', 'landed' => true, 'message' => null],
        ['type' => 'tool_use', 'tool' => 'update_profile', 'status' => 'complete'],
        ['type' => 'done'],
    ]);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Change my employment income to £82,000.'
    ), false);

    expect($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_navigate')
        ->and(collect($events)->firstWhere('type', 'capture_write_result'))->toBeNull()
        ->and(collect($events)->where('type', 'onboarding_advance')->last()['to_step'] ?? null)
        ->toBe('campaign_verify_navigate');
});

it('streams the same deduplicated acknowledgement that is persisted for a successful verify edit', function () {
    [$user, $conversation] = verifyEditFailureUser();

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function (User $streamUser, AiConversation $streamConversation) {
            yield ['type' => 'content', 'text' => 'Updated your salary to £82,000. Updated your salary to £82,000.'];
            $streamUser->annual_employment_income = 82000;
            $streamUser->save();
            yield ['type' => 'capture_write_result', 'tool' => 'update_profile', 'tool_call_id' => 'income', 'landed' => true, 'message' => null];
            $message = $streamConversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Updated your salary to £82,000.',
            ]);
            yield ['type' => 'done', 'message_id' => $message->id];
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    $mock->shouldReceive('setVerifyEditScope')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Change my salary to £82,000.'
    ), false);
    $streamed = collect($events)->where('type', 'content')->pluck('text')->first();
    $persisted = $conversation->messages()->where('role', 'assistant')->oldest('id')->firstOrFail();

    expect($streamed)->toBe($persisted->content)
        ->and(substr_count($streamed, 'Updated income'))->toBe(1)
        ->and($streamed)->toContain('employment income now £82,000');
});

// ── Fix: a question is never "original capture details" (live conversation
// 168, msg 19550) ────────────────────────────────────────────────────────
//
// At campaign_bank_accounts the user asked "How is my financial health?".
// The A1 answer path replied with a question-shaped data-gap response ("I
// need your date of birth and monthly expenditure.") — no literal "?" but
// still matched by captureResponseRequestsClarification's "i need" phrase.
// On the user's NEXT message — a genuine on-script bank answer — the merge
// machinery (mergeUnresolvedCaptureMessage) armed off that question and
// built "Original capture details: How is my financial health\nRequested
// missing details: <the real answer>", handing the model a question to
// record as a fact. It refused ("I can only help with financial planning
// questions…"), and because that refusal is itself question-shaped, the
// merge could arm again off the refusal on the NEXT turn too.

it('never merges a question as original capture details — the previous turn asked a question, not requested a missing detail', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'onboarding_fyn_selection' => 'savetax',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Turn N-1: an off-topic question mid-capture.
    $conversation->messages()->create([
        'role' => 'user',
        'content' => 'How is my financial health?',
    ]);
    // The A1 answer path's data-gap reply — question-shaped per
    // captureResponseRequestsClarification even without a literal "?".
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'To give you personalised guidance on your financial health, I need your date of birth and monthly expenditure.',
    ]);

    $capturedMessage = null;
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function (
            User $streamUser,
            AiConversation $streamConversation,
            string $streamMessage
        ) use (&$capturedMessage) {
            $capturedMessage = $streamMessage;

            yield ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'];
            yield ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 77, 'name' => 'Nationwide'];
            yield ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'landed' => true, 'message' => null];
            yield ['type' => 'content', 'text' => 'Recorded — Nationwide savings account.'];
            yield ['type' => 'done', 'message_id' => 200];
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    $received = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'One savings account with Nationwide, £2,000 balance at 4.1% interest, owned individually'
    ), false);

    // The payload sent to the LLM is the new answer ALONE — never merged
    // with the unrelated question that preceded it.
    expect($capturedMessage)
        ->not->toContain('Original capture details')
        ->not->toContain('How is my financial health')
        ->toBe('One savings account with Nationwide, £2,000 balance at 4.1% interest, owned individually');

    // The record still lands — this is a genuine capture turn, not a
    // clarification round.
    expect(collect($received)->where('type', 'capture_complete'))->toHaveCount(1)
        ->and(collect($received)->firstWhere('type', 'capture_complete')['records_created'])->toBe([
            ['type' => 'savings_account', 'id' => 77, 'name' => 'Nationwide'],
        ]);
});

it('does not arm the merge off the canned security-refusal text', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'onboarding_fyn_selection' => 'savetax',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Turn N-1: a genuine (non-question) capture answer.
    $conversation->messages()->create([
        'role' => 'user',
        'content' => 'One savings account with Nationwide, £2,000 balance at 4.1% interest, owned individually',
    ]);
    // The poisoned-turn refusal — question-shaped ("…finances?") and would
    // satisfy captureResponseRequestsClarification, but it is never a
    // genuine request for a missing capture fact.
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I can only help with financial planning questions. How can I assist with your finances?',
    ]);

    $capturedMessage = null;
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function (
            User $streamUser,
            AiConversation $streamConversation,
            string $streamMessage
        ) use (&$capturedMessage) {
            $capturedMessage = $streamMessage;

            yield ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'];
            yield ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 78, 'name' => 'Halifax'];
            yield ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'landed' => true, 'message' => null];
            yield ['type' => 'content', 'text' => 'Recorded — Halifax Cash ISA.'];
            yield ['type' => 'done', 'message_id' => 201];
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Also a Halifax Cash ISA with £5,000, owned individually'
    ), false);

    expect($capturedMessage)
        ->not->toContain('Original capture details')
        ->not->toContain('Nationwide')
        ->toBe('Also a Halifax Cash ISA with £5,000, owned individually');
});

it('never voices failure copy for a duplicate-skip alongside a landed write', function () {
    // 2026-07-23 live (round 2, msg 19858): the joint account saved and the
    // model re-called create for the already-saved current account. The
    // dedupe skip (warning + existing_id) carries landed=false and a
    // result message, so it entered the failure ledger and the user read
    // "I couldn't record anything new there" directly above
    // "Saved Joint Savings Account." A duplicate skip means the record
    // exists — it is not a failure.
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'landed' => true, 'message' => null,
            'result' => ['success' => true, 'created' => true, 'entity_id' => 558, 'entity_type' => 'savings_account', 'name' => 'Joint Savings Account']],
        ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 558, 'name' => 'Joint Savings Account'],
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
        ['type' => 'capture_write_result', 'tool' => 'create_savings_account', 'landed' => false, 'message' => null,
            'result' => ['warning' => true, 'existing_id' => 557, 'message' => "A similar record 'Current Account' already exists. The new record was not created to avoid duplication."]],
        ['type' => 'done', 'message_id' => 99],
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage(
        $user,
        $conversation,
        'Please try the joint savings account again: £12,000 at 4.2% interest, owned 50/50 with my husband Daniel.'
    ) as $event) {
        $received[] = $event;
    }

    $texts = collect($received)
        ->filter(fn (array $e) => ($e['type'] ?? null) === 'content')
        ->pluck('text');

    expect($texts->first(fn (string $t) => str_contains($t, "couldn't record") || str_contains($t, "couldn't save")))
        ->toBeNull();
});
