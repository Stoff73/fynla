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

/**
 * Capture-turn framing regression (deflection fix, June13 §6c).
 *
 * handleInlineCapture is DEFINITIONALLY a capture turn — a write intent the
 * deterministic classifier or the LLM delegate_to_capture path has already
 * cleared. It must NEVER be framed as advice: a null unifiedFocus makes
 * injectUnifiedTurnContext pick mode='advice', FynContextSelector drops the
 * CAPTURE bucket, FynCaptureTurnInstructions are never injected, and the
 * capture-turn model falls back to the security refusal ("I can only help
 * with financial planning questions…") instead of calling create_*.
 *
 * The bug: inferFocusesFromEntityTypes only mapped protection/savings/
 * retirement/investment, so property, mortgage, liability, goal, life_event,
 * and every estate entity (asset, will, trust, power_of_attorney, gift,
 * chattel, business_interest) derived a null focus → advice framing → deflect.
 *
 * This pins that a non-null focus reaches the loop (via setUnifiedOnboardingFocus)
 * for every captureable entity type.
 */
it('frames the inline capture as a capture turn (non-null focus) for every captureable entity type', function (string $entityType) {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $capturedFocus = null;
    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')
        ->andReturnUsing(function ($focus) use (&$capturedFocus) {
            if ($focus !== null) {
                $capturedFocus = $focus;
            }
        });
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            yield ['type' => 'content', 'text' => 'Recorded.'];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    /** @var OnboardingChatDirector $director */
    $director = app(OnboardingChatDirector::class);

    $context = new CaptureContext(
        reason: "user is adding their own {$entityType}",
        entityTypes: [$entityType],
    );

    iterator_to_array(
        $director->handleInlineCapture($user, $conversation, "Please add my {$entityType}", $context),
        false,
    );

    expect($capturedFocus)->not->toBeNull(
        "Capture turn for entity '{$entityType}' was framed as advice (null focus) — the capture instructions are dropped and the model deflects."
    );
})->with([
    'goal' => ['goal'],
    'life_event' => ['life_event'],
    'property' => ['property'],
    'mortgage' => ['mortgage'],
    'liability' => ['liability'],
    'asset' => ['asset'],
    'estate_gift' => ['estate_gift'],
    'chattel' => ['chattel'],
    'trust' => ['trust'],
    'will' => ['will'],
    'power_of_attorney' => ['power_of_attorney'],
    'business_interest' => ['business_interest'],
    'family_member' => ['family_member'],
    // sanity: the already-working module types must keep their focus
    'savings_account' => ['savings_account'],
    'pension' => ['pension'],
]);
