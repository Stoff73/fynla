<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
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
        ->andReturnUsing(function () use ($events) {
            foreach ($events as $event) {
                yield $event;
            }
        });
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();

    test()->instance(CoordinatingAgent::class, $mock);
}

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
    expect($conversation->messages()->where('metadata->capture_write_failed', true)->exists())->toBeTrue();
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

it('still advances a question turn whose write landed', function () {
    [$user, $conversation] = captureFailureUser();

    mockDelegatedStream([
        ['type' => 'tool_use', 'tool' => 'create_savings_account', 'status' => 'running'],
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
    expect(collect($received)->firstWhere('type', 'capture_write_result'))->toBeNull();
});
