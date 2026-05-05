<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Covers PRD FR-M14 (F3) — asset_capture off-script prevention.
 *
 * Two layers:
 *   1. OnboardingPromptBuilder::assetCaptureInstructions includes an
 *      explicit off-script guardrail telling the LLM to ignore topics
 *      outside the tool list.
 *   2. OnboardingChatDirector::handleAssetCaptureTurn applies a
 *      selective content-event filter — swallow questions (contain "?")
 *      and zero-tool-call content; preserve post-tool confirmations.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M14
 */
afterEach(function () {
    Mockery::close();
});

function runAssetCapture(User $user, AiConversation $conversation, array $events): array
{
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($events) {
            foreach ($events as $event) {
                yield $event;
            }
        });
    test()->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);

    $received = [];
    foreach ($director->handleUserMessage($user, $conversation, 'test message') as $event) {
        $received[] = $event;
    }

    return $received;
}

function makeAssetCaptureUser(): array
{
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

    return [$user, $conversation];
}

describe('OnboardingPromptBuilder off-script guardrail (FR-M14)', function () {
    it('includes an explicit off-script guardrail instructing the LLM to ignore out-of-scope topics', function () {
        $user = User::factory()->create();
        $builder = app(OnboardingPromptBuilder::class);
        $prompt = $builder->buildAssetCapturePrompt($user, 'family');

        // The FR-M14 guardrail must name property and mortgages explicitly
        // so the LLM can't hedge.
        expect($prompt)->toContain('property')
            ->and($prompt)->toContain('mortgages')
            ->and($prompt)->toMatch('/(outside the tool list|not in scope|IGNORE it silently|Ask no questions)/i');
    });

    it('forbids questions without a question mark (FR-M14 tighten)', function () {
        $user = User::factory()->create();
        $builder = app(OnboardingPromptBuilder::class);
        $prompt = $builder->buildAssetCapturePrompt($user, 'family');

        // The tightened prompt must explicitly close the loophole where the
        // LLM rewrites a question without a trailing "?". The /s (dotall)
        // flag is required because the prompt wraps across lines.
        expect($prompt)->toMatch('/not\s+with\s+a\s+question\s+mark[,\s]+not\s+without/is')
            ->and($prompt)->toMatch('/(EXACTLY ONE sentence|15 words or fewer|EMPTY text content)/i');
    });
});

describe('OnboardingChatDirector::handleAssetCaptureTurn content filter (FR-M14)', function () {
    it('swallows off-script questions even when tool calls happened', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Jane added'],
            ['type' => 'content', 'text' => 'Got it. Do you own any property?'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        // The question-mark content must be filtered out. Only director-
        // emitted content (the add_more prompt) may remain.
        expect($contentTexts)->each->not->toContain('Do you own any property');
    });

    it('preserves legitimate post-tool confirmations without a question mark', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Jane added'],
            ['type' => 'content', 'text' => 'Got it — recording those now.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        expect($contentTexts)->toContain('Got it — recording those now.');
    });

    it('swallows zero-tool-call content (empty turn acknowledgments)', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'content', 'text' => 'No holdings noted.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        expect($contentTexts)->each->not->toContain('No holdings noted.');
    });

    it('forwards pre-tool content when tool calls do follow (queued flush)', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'content', 'text' => 'Recording those now.'],
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Jane added'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );
        expect($contentTexts)->toContain('Recording those now.');
    });

    it('forwards all tool_success events regardless of content filter', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Jane added'],
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Tim added'],
            ['type' => 'content', 'text' => 'Do you want to tell me about property?'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $toolSuccessCount = count(array_filter(
            $received,
            fn ($e) => ($e['type'] ?? null) === 'tool_success'
        ));
        expect($toolSuccessCount)->toBe(2);
    });

    // ─── FR-M14 follow-up — sentence-level off-script filter ──────────────

    it('strips off-script sentences without a question mark on family selection (Test 6 regression)', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        // This is the exact failure mode documented in
        // April20Updates/smoke-test-results-local.md §"Test 6":
        // the LLM phrased questions WITHOUT a trailing `?`, bypassing
        // the old whole-event `?`-strip. Sentence-level filter drops
        // the property/mortgage/income sentences and keeps only the
        // legitimate ack.
        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Mum added'],
            ['type' => 'content', 'text' => "Got it — recording those now.\nThanks, TestF — I've added your mum (aged 72) to your family profile for protection and estate planning.\n\nWith your £50,000 gross income and £3,500 monthly outgoings, it looks like you have capacity to build protection cover or start growing savings.\n\nDo you own your home or have a mortgage If so, what's the property address, ownership share (e.g. 50% with spouse), and rough value"],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );

        // Every surviving content event must be off-script-free for a
        // family selection: no property / mortgage / home / income / etc.
        foreach ($contentTexts as $text) {
            expect($text)->not->toMatch('/\b(propert(?:y|ies)|mortgages?|rents?|incomes?|homes?|ownership|valuations?)\b/i');
        }

        // The legitimate acknowledgment must survive.
        $assetCaptureContent = array_filter(
            $contentTexts,
            fn (string $t): bool => str_contains($t, 'recording those now') || str_contains($t, 'added your mum')
        );
        expect(array_values($assetCaptureContent))->not->toBeEmpty();
    });

    it('strips sentences asking questions without a question mark', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Mum added'],
            ['type' => 'content', 'text' => 'Got it — recording those now. Do you own your home or have a mortgage'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );

        foreach ($contentTexts as $text) {
            expect($text)->not->toContain('Do you own')
                ->and($text)->not->toContain('mortgage');
        }
    });

    it('preserves property and mortgage mentions on estate selection (whitelist)', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
            'onboarding_fyn_selection' => 'estate',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_property'],
            ['type' => 'tool_success', 'tool' => 'create_property', 'summary' => 'Home recorded'],
            ['type' => 'content', 'text' => 'Got it — recording your property and mortgage now.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );

        // Estate selection allows property/mortgage vocabulary because
        // create_property + create_liability are in its tool list.
        expect($contentTexts)->toContain('Got it — recording your property and mortgage now.');
    });

    it('preserves income mentions on protection selection (whitelist)', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
            'onboarding_fyn_selection' => 'protection',
        ]);
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_protection_policy'],
            ['type' => 'tool_success', 'tool' => 'create_protection_policy', 'summary' => 'Income protection added'],
            ['type' => 'content', 'text' => 'Got it — recording your income protection policy now.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );

        // Protection selection legitimately references "income" via the
        // income-protection product name.
        expect($contentTexts)->toContain('Got it — recording your income protection policy now.');
    });

    it('keeps the legitimate first sentence and strips the off-script second sentence', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Jane added'],
            ['type' => 'content', 'text' => 'Thanks, added Jane. With your income and home mortgage you may need more cover.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );

        // First sentence ("Thanks, added Jane.") survives; second sentence
        // (contains income / home / mortgage) is dropped.
        $joined = implode(' | ', $contentTexts);
        expect($joined)->toContain('Thanks, added Jane.')
            ->and($joined)->not->toContain('income')
            ->and($joined)->not->toContain('mortgage');
    });

    it('does not strip unrelated words that happen to share stems (homework, homeowner)', function () {
        [$user, $conversation] = makeAssetCaptureUser();

        $received = runAssetCapture($user, $conversation, [
            ['type' => 'tool_use', 'tool' => 'create_family_member'],
            ['type' => 'tool_success', 'tool' => 'create_family_member', 'summary' => 'Kid added'],
            ['type' => 'content', 'text' => 'Got it — added your kid who has lots of homework lately.'],
            ['type' => 'done', 'message_id' => 1],
        ]);

        $contentTexts = array_column(
            array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'),
            'text'
        );

        // "homework" must not trigger the "home" keyword filter (word
        // boundary).
        expect($contentTexts)->toContain('Got it — added your kid who has lots of homework lately.');
    });
});
