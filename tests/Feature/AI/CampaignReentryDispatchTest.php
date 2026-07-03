<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\ScriptedAnthropicClient;

uses(RefreshDatabase::class);

/**
 * Task A3 — campaign re-entry dispatch guard.
 *
 * Pins four invariants for the predicate at AiChatController::sendMessage:
 *
 *   $inOnboarding = ($user->onboarding_completed === false || $user->active_campaign !== null)
 *       && $user->onboarding_fyn_step !== null
 *       && (bool) config('onboarding.fyn_flow_enabled', true);
 *
 * Campaign re-entry (map §4, canonical contract amendment) routes a completed
 * user with an active_campaign back through OnboardingChatDirector — the one
 * write state — so they can walk the pensioncheck funnel. onboarding_completed
 * is never modified by re-entry; the distinction is carried by active_campaign.
 *
 * Observable SSE shape used to distinguish the two dispatch paths:
 *   - Advice path: FynLoop::run emits ['type' => 'thinking'] before calling the
 *     reasoner. This event is absent from the director path.
 *   - Director path: with an empty scripted AI client the grouped_extract handler
 *     receives no capture event and emits its state-defined retry_text via
 *     emitRetry(). For base_work the retry_text is "I just need your gross
 *     annual income in GBP — could you share that?" — a director-specific string
 *     that never appears in the advice stream.
 *
 * The director path test (test 2) runs the REAL OnboardingChatDirector against
 * the scripted empty AI clients bound by the Pest global hook, exactly as
 * ProceduralVersionStampingTest and ConversationIndexPopulationTest do.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // Force the Anthropic provider so FynLoop's Planner uses the scripted client
    // already bound by the Pest global hook (empty, fast, no network).
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));
});

afterEach(function () {
    Mockery::close();
});

// ── helpers ─────────────────────────────────────────────────────────────────

/**
 * Grant ai_chat consent so sendMessage's entry gate lets the request through.
 */
function grantCampaignDispatchConsent(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

/**
 * Parse raw SSE bytes into a flat array of decoded event arrays.
 */
function parseCampaignDispatchSse(string $raw): array
{
    return collect(explode("\n\n", $raw))
        ->filter(fn ($chunk) => str_starts_with(trim($chunk), 'data:'))
        ->map(fn ($chunk) => json_decode(preg_replace('/^data:\s*/', '', trim($chunk)), true))
        ->filter()
        ->values()
        ->all();
}

/**
 * Stub CoordinatingAgent so the AdviceFyn path completes with a named sentinel.
 * Mirrors the pattern used in ConsentRuntimeCheckTest::bindAdviceFynStubGenerator.
 */
function stubAdvicePathForDispatch(string $sentinel = 'advice-dispatch-sentinel'): void
{
    test()->mock(CoordinatingAgent::class, function ($mock) use ($sentinel) {
        $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
        $mock->shouldReceive('chatWithPromptOverride')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function () use ($sentinel) {
                return (function () use ($sentinel) {
                    yield ['type' => 'content', 'text' => $sentinel];
                    yield ['type' => 'done'];
                })();
            });
    });
}

// ── Test 1: completed user, active_campaign=null → advice path ───────────────

it('routes a completed user with no active_campaign to the advice path', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => null,
        'onboarding_fyn_step' => null,
        'is_preview_user' => false,
    ]);
    grantCampaignDispatchConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    stubAdvicePathForDispatch('advice-no-campaign');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignDispatchSse($response->streamedContent());
    $types = array_column($events, 'type');

    // FynLoop::run emits thinking before the reasoner — advice path only.
    expect($types)->toContain('thinking');
    expect(array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text'))
        ->toContain('advice-no-campaign');
});

// ── Test 2: completed user, active_campaign='pensioncheck', step set → director ──
//
// The real OnboardingChatDirector runs against the empty scripted AI clients
// bound by the Pest global hook. With base_work (grouped_extract) and no capture
// from the empty LLM, the director calls emitRetry(), which yields the state's
// retry_text. FynLoop::stream (used by the director) does NOT emit a 'thinking'
// event; FynLoop::run (used by the advice path) does. Asserting the absence of
// 'thinking' is the dispatch discriminator.

it('routes a completed user with active_campaign and a non-null step to the director path', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => 'base_work', // real existing state; campaign2 states not yet built
        'is_preview_user' => false,
    ]);
    grantCampaignDispatchConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    // No mock — real OnboardingChatDirector with empty scripted AI clients,
    // matching the pattern used by ProceduralVersionStampingTest.

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignDispatchSse($response->streamedContent());
    $types = array_column($events, 'type');

    // 'thinking' is emitted only by FynLoop::run (advice path); the director
    // goes through FynLoop::stream, which has no thinking event.
    expect($types)->not->toContain('thinking');

    // The director's grouped_extract handler received no capture from the empty
    // LLM client and fell through to emitRetry, yielding base_work's retry_text.
    $texts = array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text');
    expect(implode(' ', $texts))->toContain('gross annual income');

    // Director handled the empty-LLM case cleanly — no error event.
    expect($types)->not->toContain('error');
});

// ── Test 3: completed user, active_campaign set but step=null → advice path ─

it('routes to advice when active_campaign is set but onboarding_fyn_step is null (paused mid-campaign)', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => null, // paused — step was cleared
        'is_preview_user' => false,
    ]);
    grantCampaignDispatchConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    stubAdvicePathForDispatch('advice-paused');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignDispatchSse($response->streamedContent());
    $types = array_column($events, 'type');

    expect($types)->toContain('thinking');
    expect(array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text'))
        ->toContain('advice-paused');
});

// ── Test 4: fyn_flow_enabled=false → advice path regardless of campaign ──────

it('routes to advice when fyn_flow_enabled is false even if active_campaign and step are set', function (): void {
    config()->set('onboarding.fyn_flow_enabled', false);

    $user = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => 'base_work',
        'is_preview_user' => false,
    ]);
    grantCampaignDispatchConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    stubAdvicePathForDispatch('advice-flow-disabled');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignDispatchSse($response->streamedContent());
    $types = array_column($events, 'type');

    expect($types)->toContain('thinking');
    expect(array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text'))
        ->toContain('advice-flow-disabled');
});
