<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Models\AiConversation;
use App\Models\OnboardingProgress;
use App\Models\PointAward;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\ScriptedAnthropicClient;

uses(RefreshDatabase::class);

/**
 * Task A5 — campaign exit paths clear active_campaign.
 *
 * Three invariants:
 *
 *   1. Re-entry user hits campaign_terminal via campaign_synthesis advance →
 *      active_campaign=null, onboarding_fyn_step=null, onboarding_completed
 *      remains true, and no new point_awards row is created for the terminal
 *      completion award (dedup key "onboarding:campaign_terminal").
 *
 *   2. Fresh user (onboarding_completed=false) reaches campaign_terminal via the
 *      same advance → active_campaign=null, onboarding_fyn_step=null, and the
 *      normal completion path fires (onboarding_completed=true, completed_at set).
 *
 *   3. Any re-entry user presses "something_else" (pause) →
 *      active_campaign=null, onboarding_fyn_step=null; subsequent messages
 *      route to the advice path.
 *
 * Terminal is reached by placing the user at campaign_synthesis (turn_type=advice,
 * constant next=STATE_CAMPAIGN_TERMINAL). The director handles any non-empty
 * message at an advice state via interpretAnswer (free_text, no parser) →
 * getNextStateId → emitTerminalNavigationTurn. No LLM round-trip is required.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));
});

afterEach(function () {
    Mockery::close();
});

// ── helpers ─────────────────────────────────────────────────────────────────

function grantExitTestConsent(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

function makeReentryExitUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
        'active_campaign' => null,
    ], $overrides));

    grantExitTestConsent($user);

    return $user;
}

function makeExitCampaignConversation(User $user, string $campaign = 'pensioncheck'): AiConversation
{
    return AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'metadata' => ['source' => 'fyn_onboarding', 'campaign' => $campaign],
    ]);
}

// ── Test 1: re-entry user hits campaign terminal ─────────────────────────────

it('clears active_campaign and does not re-fire completion side effects when a re-entry user hits the campaign terminal', function (): void {
    // Seed a known completed_at so we can assert it is byte-identical after
    // the stream. A broken guard calls $user->onboarding_completed_at = now(),
    // which overwrites this value.
    $originalCompletedAt = now()->subDays(10)->startOfSecond();

    $user = makeReentryExitUser([
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign_synthesis',
        'onboarding_completed_at' => $originalCompletedAt,
    ]);

    // Pre-create the PointAward row for "onboarding:campaign_terminal" to
    // simulate the user having completed the terminal on a prior re-entry run.
    PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'onboarding',
        'dedup_key' => 'onboarding:campaign_terminal',
        'points' => (int) config('gamification.points.onboarding_answer', 10),
        'meta' => [],
    ]);

    $conv = makeExitCampaignConversation($user);

    // Any non-empty message at campaign_synthesis advances via interpretAnswer
    // (free_text, no parser) → getNextStateId → emitTerminalNavigationTurn.
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'ok']);

    $response->assertOk();
    // Consume the SSE stream so the generator body — including the user.save()
    // calls that happen after the final yield — actually executes.
    $response->streamedContent();
    $user->refresh();

    // Campaign exit: active_campaign must be cleared unconditionally.
    expect($user->active_campaign)->toBeNull();
    // Terminal always nulls onboarding_fyn_step.
    expect($user->onboarding_fyn_step)->toBeNull();
    // Re-entry user was already completed — must remain true.
    expect($user->onboarding_completed)->toBeTrue();

    // Guard-dependent assertion 1: onboarding_completed_at must be byte-identical
    // to the seeded value. A broken guard calls $user->onboarding_completed_at = now()
    // which overwrites the original timestamp.
    expect($user->onboarding_completed_at->toIso8601String())
        ->toBe($originalCompletedAt->toIso8601String());

    // Guard-dependent assertion 2: no OnboardingProgress row for campaign_terminal.
    // OnboardingProgress::create inside recordProgress is non-deduped — a broken
    // guard fires recordProgress unconditionally, creating a duplicate progress row.
    expect(
        OnboardingProgress::where('user_id', $user->id)
            ->where('step_name', 'campaign_terminal')
            ->count()
    )->toBe(0);
});

// ── Test 2: fresh user hits campaign terminal ─────────────────────────────────

it('clears active_campaign and marks onboarding complete when a fresh user hits the campaign terminal', function (): void {
    // Fresh user (onboarding_completed=false) who arrived via campaign.
    // A reentry-enabled campaign start stamps active_campaign for fresh users
    // too — the terminal exit must unconditionally clear it.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign_synthesis',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    grantExitTestConsent($user);

    $conv = makeExitCampaignConversation($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'ok']);

    $response->assertOk();
    $response->streamedContent();
    $user->refresh();

    // active_campaign cleared even for fresh users.
    expect($user->active_campaign)->toBeNull();
    // Step cleared.
    expect($user->onboarding_fyn_step)->toBeNull();
    // Normal completion path fires (fresh user, not previously completed).
    expect($user->onboarding_completed)->toBeTrue();
    expect($user->onboarding_completed_at)->not->toBeNull();
});

// ── Test 3: something_else pause clears active_campaign ──────────────────────

it('clears active_campaign when a re-entry user pauses via the something_else action', function (): void {
    $user = makeReentryExitUser([
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_step' => 'base_work',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);

    $conv = makeExitCampaignConversation($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conv->id}/action", ['action' => 'something_else']);

    $response->assertOk();
    $response->streamedContent();
    $user->refresh();

    // Pause must clear active_campaign so the next message routes to advice,
    // not back to the director (which would gate on active_campaign being set).
    expect($user->active_campaign)->toBeNull();
    // Existing pause behaviour: step is nulled so AiChatController predicate
    // falls through to advice on the next message.
    expect($user->onboarding_fyn_step)->toBeNull();
});
