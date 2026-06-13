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
    expect($rendered)->toContain('Outside the QUESTION EXCEPTION above, do NOT ask any question');
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
        $endMarker = 'Outside the QUESTION EXCEPTION above, do NOT ask any question';
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
    it('emits the answer and the scripted retry when a question yields no extraction', function () {
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
        // the no-capture path fires emitRetry.
        $received = driveCaptureTurn($user, $conversation, 'what do you mean by marital status?', [
            ['type' => 'content', 'text' => 'Marital status means whether you are single, married, or in a civil partnership. It affects allowances you can share.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        $joined = implode(' | ', $contentTexts);

        // Both the answer AND the scripted retry are present.
        $retry = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL)['retry_text'];
        expect($joined)->toContain('Marital status means whether you are single')
            ->and($joined)->toContain($retry);
    });

    it('strips personal figures from a grouped_extract answer but keeps the retry', function () {
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

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        $joined = implode(' | ', $contentTexts);

        $retry = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL)['retry_text'];
        expect($joined)->toContain('Your income sets which tax band applies.')
            ->and($joined)->not->toContain('£110,000')
            ->and($joined)->toContain($retry);
    });
});
