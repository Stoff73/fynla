<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Fyn\FynCaptureTurnInstructions;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A1 — answer-the-user-first. A capture turn must answer a user's question
 * (definitional/conceptual only, never their personal figures) before
 * resuming capture. These cover the prompt-template half of the change and
 * the legacy/unified lockstep (D4).
 *
 * Plan: Task 15 — "Answer-the-user-first" (insight-quality-track1).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});
it('instructs the model to answer a user question before resuming capture', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    expect($rendered)
        ->toContain('QUESTION EXCEPTION')
        ->toContain('ANSWER IT FIRST')
        ->toContain('re-ask the capture question');
});

it('keeps the no-personal-figures rule inside the question exception', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    expect($rendered)->toContain('never quote the user\'s own figures');
});

it('keeps the deferred-figures promise inside the question exception', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    // The exception must still promise the user their numbers later rather
    // than computing them in the capture turn.
    expect($rendered)->toContain("I'll show you what that means for your numbers at the end");
});

it('softens the two FR-M14 absolutes that conflict with the exception', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    // The blanket "do NOT ask follow-up questions" is gone (the exception
    // governs questions now)...
    expect($rendered)->not->toContain('do NOT ask follow-up questions');

    // ...and the FR-M14 guardrail's "Do NOT ask any question" is now scoped
    // to everything OUTSIDE the exception, so the protection survives.
    expect($rendered)->toContain('Outside the QUESTION EXCEPTION and')
        ->and($rendered)->toContain('CAPTURE ACCURACY RULE above, do NOT ask any question');
});

it('requires explicit ownership and ISA subtype before creating an asset', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    expect($rendered)->toContain('CAPTURE ACCURACY RULE')
        ->and($rendered)->toContain('never infer an ISA subtype or asset ownership')
        ->and($rendered)->toContain('Never convert a missing ownership answer to individual')
        ->and($rendered)->toContain('never convert a bare')
        ->and($rendered)->toContain('ISA to a Cash ISA');
});

it('keeps the unified and legacy capture templates in lockstep', function () {
    // The unified template (FynCaptureTurnInstructions) was lifted verbatim
    // from OnboardingPromptBuilder Layer 3 (D4). The QUESTION EXCEPTION block
    // carries no dynamic slots, so it must be byte-identical in both rendered
    // strings. Extract the block from each and compare.
    $unified = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    $builder = app(OnboardingPromptBuilder::class);
    $legacy = $builder->buildAssetCapturePrompt(
        User::factory()->create(['onboarding_fyn_selection' => 'savetax']),
        'savetax'
    );

    $extract = function (string $text): string {
        // From the QUESTION EXCEPTION heading through to the softened FR-M14
        // sentence, so the parity window covers the exception block AND both
        // softened absolutes (the "Do NOT greet" line minus follow-up
        // questions, and the "Outside the QUESTION EXCEPTION above" prefix).
        // Everything in this window is slot-free, so byte-identity holds.
        $endMarker = 'CAPTURE ACCURACY RULE above, do NOT ask any question';
        $start = strpos($text, 'QUESTION EXCEPTION');
        $end = strpos($text, $endMarker);
        expect($start)->not->toBeFalse();
        expect($end)->not->toBeFalse();

        return substr($text, $start, ($end - $start) + strlen($endMarker));
    };

    expect($extract($unified))->toBe($extract($legacy));
});

it('keeps the A2 ack-hygiene block in lockstep across both templates', function () {
    // A2 — the ack-hygiene sentence ("states WHAT was recorded …" / silence
    // on zero tools) is slot-free, so it must be byte-identical in the
    // unified and legacy capture templates. It already falls inside the
    // QUESTION EXCEPTION lockstep window above, but a dedicated second
    // extraction window pins its parity explicitly so a later edit that
    // shrinks the primary window cannot silently desync the ack block.
    $unified = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    $builder = app(OnboardingPromptBuilder::class);
    $legacy = $builder->buildAssetCapturePrompt(
        User::factory()->create(['onboarding_fyn_selection' => 'savetax']),
        'savetax'
    );

    $extractAck = function (string $text): string {
        $startMarker = 'Keep your text output to a single short confirmation sentence';
        $endMarker = 'the user\'s question (QUESTION EXCEPTION above) or stay silent.';
        $start = strpos($text, $startMarker);
        $end = strpos($text, $endMarker);
        expect($start)->not->toBeFalse();
        expect($end)->not->toBeFalse();

        return substr($text, $start, ($end - $start) + strlen($endMarker));
    };

    expect($extractAck($unified))->toBe($extractAck($legacy));
});

// ─── Director wiring (A1) ──────────────────────────────────────────────────

afterEach(function () {
    Mockery::close();
});

function driveCaptureTurn(User $user, AiConversation $conversation, string $message, array $events): array
{
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function () use ($events) {
            foreach ($events as $event) {
                yield $event;
            }
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    test()->instance(CoordinatingAgent::class, $mock);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, $message) as $event) {
        $received[] = $event;
    }

    return $received;
}

describe('delegated capture turn answers a question (A1)', function () {
    it('forwards a definitional answer on a zero-tool-call turn when the user asked a question', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
            'onboarding_fyn_selection' => 'savetax',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        $received = driveCaptureTurn($user, $conversation, "what's salary sacrifice?", [
            ['type' => 'content', 'text' => 'Salary sacrifice means your employer pays part of your salary into your pension instead. It saves National Insurance for both of you.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        $joined = implode(' | ', $contentTexts);

        // The answer survives even though no create_* tool fired — the old
        // behaviour dropped all zero-tool-call prose.
        expect($joined)->toContain('Salary sacrifice means your employer pays part of your salary');
    });

    it('strips the users own figures from a delegated answer turn', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
            'onboarding_fyn_selection' => 'savetax',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        $received = driveCaptureTurn($user, $conversation, 'how much would salary sacrifice save me?', [
            ['type' => 'content', 'text' => 'Salary sacrifice lowers your taxable pay and saves National Insurance. Based on your £110,000 salary you would save £2,200.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        $joined = implode(' | ', $contentTexts);

        expect($joined)->toContain('Salary sacrifice lowers your taxable pay')
            ->and($joined)->not->toContain('£110,000')
            ->and($joined)->not->toContain('£2,200');
    });

    it('still drops zero-tool-call prose when the user did NOT ask a question (default path)', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
            'onboarding_fyn_selection' => 'family',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        $received = driveCaptureTurn($user, $conversation, 'nothing else', [
            ['type' => 'content', 'text' => 'No holdings noted.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );

        // Unchanged: a zero-tool-call, no-question turn drops its prose.
        expect($contentTexts)->each->not->toContain('No holdings noted.');
    });

    it('does not advance the state machine on an answer-only turn', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
            'onboarding_fyn_selection' => 'savetax',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        $received = driveCaptureTurn($user, $conversation, "what's salary sacrifice?", [
            ['type' => 'content', 'text' => 'Salary sacrifice means your employer pays part of your salary into your pension instead.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        // QUESTION EXCEPTION: a question turn that captured nothing stays on
        // the capture state — no advance to add_more, step pointer unchanged.
        expect($user->fresh()->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);

        $advanceEvents = array_filter(
            $received,
            fn ($e) => ($e['type'] ?? null) === 'onboarding_advance'
        );
        expect($advanceEvents)->toBeEmpty();
    });
});

describe('grouped_extract turn answers a question before re-asking (A1)', function () {
    // Onboarding Interruption Intelligence Task 6 — emitRetry tries
    // handleInterruption before falling back to the blind scripted
    // retry_text (OnboardingChatDirector.php ~line 2518). BUT: when the A1
    // buffer (handleGroupedExtractTurn, ~line 2352) has already voiced a
    // (deliberately figure-redacted) answer to the user's question this
    // turn, a subsequent independently-sourced handleInterruption advice
    // answer would duplicate it and could unredact figures A1 withheld — so
    // emitRetry's answerAlreadyVoiced guard skips handleInterruption
    // entirely in that case and falls straight to the scripted retry_text
    // tail. These two cases exercise that guard: exactly one answer (the A1
    // one) plus the retry text, never a second advice-loop answer.
    it('emits only the extraction turn\'s own A1 answer plus the retry text — the interruption dispatcher is suppressed', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_PERSONAL,
            'onboarding_fyn_selection' => 'savetax',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        // Model answers the question but emits no onboarding_field_captured —
        // the no-capture path falls into emitRetry, which would try
        // handleInterruption first, but the guard under test skips that
        // because A1 already answered this question this turn.
        $received = driveCaptureTurn($user, $conversation, 'what do you mean by marital status?', [
            ['type' => 'content', 'text' => 'Marital status means whether you are single, married, or in a civil partnership. It affects allowances you can share.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_values(array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        ));

        // Exactly two content events: the A1 answer, then the scripted
        // retry text — never a duplicate from the (suppressed) interruption
        // dispatcher's own advice call.
        $retry = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL)['retry_text'];
        expect($contentTexts)->toHaveCount(2);
        expect($contentTexts[0])->toContain('Marital status means whether you are single');
        expect($contentTexts[1])->toBe($retry);

        // The walk stayed on the grouped-extract step — no blind advance.
        expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
    });

    it('strips personal figures from the grouped_extract turn own A1 answer and still emits the retry', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_PERSONAL,
            'onboarding_fyn_selection' => 'savetax',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        // NOTE: the personal-figure sentence must not name an allowance/limit/
        // threshold/band — those words trigger the statutory-definition
        // carve-out and the sentence would legitimately survive.
        $received = driveCaptureTurn($user, $conversation, 'why does my income matter?', [
            ['type' => 'content', 'text' => 'Your income sets which tax band applies. Based on your £110,000 salary you would pay extra tax.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_values(array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        ));

        // The FIRST content event is the grouped_extract turn's own A1
        // buffer — filterOffScriptContent(allowAnswer: true) still strips
        // the user's personal figure from it.
        expect($contentTexts[0] ?? '')->toContain('Your income sets which tax band applies.')
            ->and($contentTexts[0] ?? '')->not->toContain('£110,000');

        // Because A1 already answered, emitRetry's guard suppresses the
        // interruption dispatcher's advice-mode call and falls straight to
        // the scripted retry text instead — exactly one answer, never a
        // duplicate.
        $retry = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL)['retry_text'];
        expect($contentTexts[1] ?? null)->toBe($retry);
    });
});
