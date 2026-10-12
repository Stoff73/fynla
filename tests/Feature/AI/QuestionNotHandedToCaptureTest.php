<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * Fix plan T5. With the handoff rule scoped to statements (#1217), the csjones
 * walk still saw gpt-6-luna hand a pure question to capture: "What is 6% + 8%
 * of £72,000 a year? …" became `work_details` ("a possible annual income
 * figure that may be a correction") and a blank work form opened (Alex 502,
 * conversation 594, 2026-10-12). Write safety is enforced at dispatch, not by
 * prompt wording (canonical contract), so a handoff on a question is refused
 * back to the model, which answers.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
});

function sendToFyn(User $user, string $message): array
{
    $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Chat', 'status' => 'active', 'model_used' => '']);
    $body = test()->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => $message])->assertOk()->streamedContent();

    return [$conversation->fresh(), $body];
}

it('answers a question the model tried to hand to capture', function (): void {
    FynStreamHarness::fake()
        ->textTurn('') // the CoALA planner's own call (Loop/Planner.php)
        ->toolTurn('delegate_to_capture', ['reason' => 'Alex has supplied a possible annual income figure.', 'entity_types' => ['work_details']], 'toolu_q')
        ->textTurn('6% + 8% is 14%, and 14% of £72,000 is £10,080 a year.')
        ->bind();

    [$conversation, $body] = sendToFyn($this->user, 'What is 6% + 8% of £72,000 a year? And is a 50/50 split the same as half & half?');
    $last = $conversation->messages()->where('role', 'assistant')->latest('id')->first();

    expect($last?->content)->toBe('6% + 8% is 14%, and 14% of £72,000 is £10,080 a year.')
        ->and($body)->not->toContain('"type":"capture_form"')
        ->and($last?->persona)->toBe('advice')
        ->and($last?->metadata['turn_intent'] ?? null)->not->toBe('verify_prompt');
});

it('still hands a stated change to capture', function (): void {
    FynStreamHarness::fake()
        ->textTurn('')
        ->toolTurn('delegate_to_capture', ['reason' => 'User states a new salary.', 'entity_types' => ['work_details']], 'toolu_s')
        ->textTurn('Noted.')
        ->bind();

    [$conversation] = sendToFyn($this->user, 'My salary went up to £80,000 last month');

    expect($conversation->messages()->where('role', 'assistant')->latest('id')->first()?->persona)->not->toBe('advice');
});

it('still hands a what-if request to the scenario capture (S0.5.r)', function (): void {
    FynStreamHarness::fake()
        ->textTurn('')
        ->toolTurn('delegate_to_capture', ['reason' => 'User wants a what-if scenario.', 'entity_types' => ['what_if_scenario']], 'toolu_w')
        ->textTurn('Here is the scenario.')
        ->bind();

    [$conversation] = sendToFyn($this->user, 'Run a what-if: if I paid an extra £20,000 a year into my pension until 60, what would my pot be?');

    expect($conversation->messages()->where('role', 'assistant')->latest('id')->first()?->persona)->not->toBe('advice');
});
