<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\ValueObjects\CaptureContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Two-Fyn collapse (Sprint 0.3): the old post-onboarding orchestrator + invoker
 * are gone. Inline data-capture now lives on OnboardingChatDirector::handleInlineCapture.
 *
 * This test pins the behaviour that survives the collapse:
 *   1. Onboarding layout / quick-reply events are stripped before reaching
 *      the frontend (handoff invisibility, INV-2.4.1 / INV-2.4.2).
 *   2. fill_form events pass through so the frontend can queue them.
 *   3. content / done events pass through unchanged.
 *   4. No `persona_state_change` event is ever emitted (INV-2.4.1).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('strips onboarding_layout_change and quick_replies, passes fill_form and content through, emits no persona_state_change', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'onboarding',
        'title' => 'Onboarding',
    ]);

    $agent = Mockery::mock(CoordinatingAgent::class);
    // Flag-gated collaborator call under FYN_PROMPT_ARCH=unified (now the
    // default): handleInlineCapture carries the onboarding focus for the
    // turn so the CAPTURE bucket is selected. Zero-call-satisfied under
    // legacy — non-weakening (other expectations stay strict).
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            yield ['type' => 'onboarding_layout_change', 'layout' => 'capture'];
            yield ['type' => 'content', 'text' => 'Got it — recorded your SIPP.'];
            yield ['type' => 'fill_form', 'entity_type' => 'dc_pension', 'fields' => ['provider' => 'Scottish Widows']];
            yield ['type' => 'quick_replies', 'replies' => ['Yes', 'No']];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    /** @var OnboardingChatDirector $director */
    $director = app(OnboardingChatDirector::class);

    $context = new CaptureContext(
        reason: 'user volunteered a pension mid-onboarding',
        entityTypes: ['dc_pension'],
    );

    $events = [];
    foreach ($director->handleInlineCapture($user, $conversation, 'I have a Scottish Widows SIPP £50k', $context) as $event) {
        $events[] = $event;
    }

    $types = array_map(fn (array $e) => $e['type'] ?? '', $events);

    // Layout + quick-reply events stripped.
    expect($types)->not->toContain('onboarding_layout_change');
    expect($types)->not->toContain('quick_replies');

    // Content, fill_form, done pass through.
    expect($types)->toContain('content');
    expect($types)->toContain('fill_form');
    expect($types)->toContain('done');

    // Handoff invisibility — no persona_state_change ever emitted.
    expect($types)->not->toContain('persona_state_change');
});
