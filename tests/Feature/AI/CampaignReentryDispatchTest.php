<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingChatDirector;
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
 * Campaign re-entry (map §4, canonical amendment ref) routes a completed user
 * with an active_campaign back through OnboardingChatDirector — the one write
 * state — so they can walk the pensioncheck flow. onboarding_completed is never
 * modified by re-entry; the distinction is carried by active_campaign alone.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // Force Anthropic provider so FynLoop's Planner uses the scripted client
    // bound by the Pest global hook (empty, fast, no network).
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));
});

afterEach(function () {
    Mockery::close();
});

// ── helpers ─────────────────────────────────────────────────────────────────

/**
 * Grant ai_chat consent so sendMessage's entry gate passes.
 */
function grantCampaignTestConsent(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

/**
 * Parse raw SSE bytes into a list of decoded event arrays.
 */
function parseCampaignTestSse(string $raw): array
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
 * Call before making the HTTP request.
 */
function stubAdvicePath(string $marker = 'advice-path-sentinel'): void
{
    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use ($marker) {
            return (function () use ($marker) {
                yield ['type' => 'content', 'text' => $marker];
                yield ['type' => 'done'];
            })();
        });
    app()->instance(CoordinatingAgent::class, $agent);
}

/**
 * Stub OnboardingChatDirector so the director path completes with a named sentinel.
 */
function stubDirectorPath(string $marker = 'director-path-sentinel'): void
{
    $director = Mockery::mock(OnboardingChatDirector::class);
    $director->shouldReceive('handleUserMessage')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use ($marker) {
            return (function () use ($marker) {
                yield ['type' => 'content', 'text' => $marker];
                yield ['type' => 'done'];
            })();
        });
    app()->instance(OnboardingChatDirector::class, $director);
}

// ── Test 1: completed user, active_campaign=null → advice path ───────────────

it('routes a completed user with no active_campaign to the advice path', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => null,
        'onboarding_fyn_step' => null,
        'is_preview_user' => false,
    ]);
    grantCampaignTestConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    stubAdvicePath('advice-handled');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignTestSse($response->streamedContent());
    $texts = array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text');

    expect($texts)->toContain('advice-handled');
});

// ── Test 2: completed user, active_campaign='pensioncheck', step set → director path ──

it('routes a completed user with active_campaign and a non-null step to the director path', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => 'base_work', // real existing state id; campaign2 states not yet built
        'is_preview_user' => false,
    ]);
    grantCampaignTestConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    stubDirectorPath('director-handled');
    stubAdvicePath('advice-fallback'); // safety net if dispatch goes to advice instead

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignTestSse($response->streamedContent());
    $texts = array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text');

    expect($texts)->toContain('director-handled');
});

// ── Test 3: completed user, active_campaign set but step=null → advice path ─

it('routes to advice when active_campaign is set but onboarding_fyn_step is null (paused mid-campaign)', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => null, // paused — step was cleared
        'is_preview_user' => false,
    ]);
    grantCampaignTestConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    stubAdvicePath('advice-handled-paused');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignTestSse($response->streamedContent());
    $texts = array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text');

    expect($texts)->toContain('advice-handled-paused');
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
    grantCampaignTestConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    stubAdvicePath('advice-handled-disabled');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello']);

    $response->assertOk();
    $events = parseCampaignTestSse($response->streamedContent());
    $texts = array_column(array_filter($events, fn ($e) => ($e['type'] ?? '') === 'content'), 'text');

    expect($texts)->toContain('advice-handled-disabled');
});
