<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\AdviceFyn;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * Walked locally 2026-10-05 (user 125, conversations 325 and 326): "My Walk
 * Bank balance is £21,500 now" got the prompt-injection refusal, and "My
 * salary went up to £70,000" got typed questions and "I'll update your
 * employment income", with nothing saved. The advice turn's allow-list names
 * delegate_to_capture (AdviceFyn::buildToolList), but the pool it filtered
 * held only the base catalogue and the setup extraction tools, so the one way
 * advice hands a change to capture was never offered to the model.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
});

it('offers the advice model the handoff to capture, and no write tool', function (): void {
    FynStreamHarness::fake()
        // The CoALA Planner's own call takes the first turn (Loop/Planner.php).
        ->textTurn('')
        ->textTurn('Noted.')
        ->bind();
    $conversation = AiConversation::create(['user_id' => $this->user->id, 'title' => 'Change', 'status' => 'active', 'model_used' => '']);

    $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'My salary went up to £70,000'])->assertOk()->streamedContent();

    $offered = collect(app(Client::class)->messages->calls)
        ->map(fn (array $args): array => array_map(fn (array $tool): string => (string) ($tool['name'] ?? ''), (array) ($args['tools'] ?? [])))
        ->first(fn (array $names): bool => count($names) > 1);

    expect($offered)->toContain('delegate_to_capture')
        ->and(array_values(array_intersect($offered, AdviceFyn::WRITE_TOOLS)))->toBe([]);
});
