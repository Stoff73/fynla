<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\TaxConfigService;
use Tests\Support\Fyn\FynStreamHarness;

beforeEach(function () {
    // The real chat loop reads the daily token backstop from the tier store
    // (HasAiGuardrails); a factory user resolves to the free tier.
    TierConfiguration::create(tierConfigFixture('free'));

    // The chat path resolves TaxConfigService, whose store memoises the active
    // config and can latch null before the row exists. Re-create + forget the
    // singleton (the working pattern from CrossModuleIntegrationTest).
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
});

/*
 * CoALA Phase 5 item 4 — stream-mock harness proof.
 *
 * Drives HasAiChat's real tool-use loop with a SCRIPTED Anthropic stream
 * (binds a fake Anthropic\Client whose messages->createStream replays SDK
 * events). This is the harness the FynLoop extraction is TDD'd against, and it
 * retro-fits the e2e coverage gap the handover flagged for items 1-2 (the loop's
 * telemetry persistence + in-loop ground gate had unit coverage only).
 */

it('streams a scripted text-only assistant turn through the real chat loop', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Harness',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->textTurn('Your total net worth is £250,000.')
        ->bind();

    $events = iterator_to_array(
        app(CoordinatingAgent::class)->chat($user, $conversation, 'What is my net worth?'),
        preserve_keys: false,
    );

    $content = collect($events)->where('type', 'content')->pluck('text')->implode('');

    expect($content)->toContain('Your total net worth is £250,000.')
        ->and(collect($events)->contains(fn ($e) => ($e['type'] ?? null) === 'done'))->toBeTrue();

    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
    ]);
});

it('strips a write surface emitted in advice mode inside the loop and never executes it', function () {
    // Closes the e2e gap the handover flagged for item 2: the in-loop ground
    // gate (ActionDispatcher, wired at HasAiChat dispatch) only had unit
    // coverage. Here a write tool is scripted in the advice persona and must be
    // stripped — audited, never executed — before a final text turn.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Harness gate',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', ['provider' => 'Halifax', 'balance' => 5000])
        ->textTurn('I can’t add accounts here — head to the savings module to do that.')
        ->bind();

    iterator_to_array(
        app(CoordinatingAgent::class)->chatWithPromptOverride(
            $user,
            $conversation,
            'Add a Halifax savings account with £5,000',
            null,   // currentRoute
            null,   // systemPromptOverride
            null,   // allowedTools
            true,   // persistUserMessage
            null,   // toolsListOverride
            'advice', // personaOverride — the read-only state
        ),
        preserve_keys: false,
    );

    // The write surface was rejected before execution: no account created.
    $this->assertDatabaseMissing('savings_accounts', ['user_id' => $user->id]);

    // The blocked attempt is forensically recorded as stripped.
    $this->assertDatabaseHas('ai_audit_events', [
        'user_id' => $user->id,
        'tool_name' => 'create_savings_account',
        'status' => 'stripped',
    ]);
});
