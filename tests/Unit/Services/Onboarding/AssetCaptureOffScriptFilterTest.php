<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
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
});
