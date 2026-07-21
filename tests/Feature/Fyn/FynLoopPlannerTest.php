<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\AI\Loop\FynLoop;
use App\Services\AI\Loop\SessionMode;
use App\Services\AI\Memory\FynMemoryStore;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\File;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CoALA Phase 5 item 5 increment 2 — the planner driving FynLoop::run end-to-end
 * through the real chat loop (Option A: planner routes; reasoner keeps tool-use).
 *
 * The harness scripts the planner's forced `plan` tool call FIRST (one
 * createStream pop), then the reasoner's stream — proving the two-call shape on
 * the live dispatch path without a network call.
 */
beforeEach(function () {
    TierConfiguration::updateOrCreate(['tier' => 'free'], tierConfigFixture('free'));
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
});

function fynLoopUser(): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Planner loop',
        'message_count' => 0,
    ]);

    return [$user, $conversation];
}

it('plans reason then streams the reasoner answer through the loop', function () {
    [$user, $conversation] = fynLoopUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->textTurn('Your total net worth is £250,000.')
        ->bind();

    $events = iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'What is my net worth?', null, null),
        preserve_keys: false,
    );

    $content = collect($events)->where('type', 'content')->pluck('text')->implode('');

    expect($content)->toContain('Your total net worth is £250,000.')
        ->and(collect($events)->contains(fn ($e) => ($e['type'] ?? null) === 'done'))->toBeTrue();

    // The reasoner ran and persisted the assistant turn (proves reason routed to
    // the streamed reasoner, not the canonical defer path).
    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
    ]);
});

it('records reasoner + planner cost-attribution rows and emits thinking (FR-M11/M14)', function () {
    [$user, $conversation] = fynLoopUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->textTurn('Your total net worth is £250,000.')
        ->bind();

    $events = iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'net worth?', null, null),
        preserve_keys: false,
    );

    // FR-M14 — a thinking event is surfaced while the planner runs.
    expect(collect($events)->pluck('type'))->toContain('thinking');

    // FR-M11 — both the reasoner turn AND the planner's own call are attributed.
    $this->assertDatabaseHas('ai_cost_attribution', [
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'stage' => 'reasoner',
        'action_type' => 'reason',
        'session_mode' => 'advice',
    ]);
    $this->assertDatabaseHas('ai_cost_attribution', [
        'conversation_id' => $conversation->id,
        'stage' => 'planner',
        'action_type' => 'reason',
        'session_mode' => 'advice',
    ]);
});

it('writes an episode when the planner emits a learn action (FR-M2)', function () {
    [$user, $conversation] = fynLoopUser();
    $base = sys_get_temp_dir().'/fyn-loop-mem-'.uniqid();
    config(['fyn.memory.episodic_path' => $base]);

    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'learn', 'store' => 'episodic', 'payload' => ['summary' => 'User wants to retire at 60.']])
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->textTurn('Noted — I will remember that.')
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'I want to retire at 60', null, null),
        preserve_keys: false,
    );

    $episodes = app(FynMemoryStore::class)->recall($user->id);

    expect($episodes)->toHaveCount(1)
        ->and($episodes[0]['body'])->toContain('retire at 60')
        ->and($episodes[0]['meta']['conversation_id'])->toBe($conversation->id);

    File::deleteDirectory($base);
});

it('emits the canonical defer response on a no_action plan', function () {
    [$user, $conversation] = fynLoopUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'no_action'])
        ->bind();

    $events = iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'hello', null, null),
        preserve_keys: false,
    );

    $content = collect($events)->where('type', 'content')->pluck('text')->implode('');

    expect($content)->toContain('I need a little more time')
        ->and(collect($events)->contains(fn ($e) => ($e['type'] ?? null) === 'done'))->toBeTrue();
});
