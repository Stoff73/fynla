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
 * Walked 2026-10-03 (conversation 297): a turn that reached the tool-call cap
 * showed every round's sentence run together ("…recorded properly.I'll pass
 * this…") while the stored reply kept only the last round, because the forced
 * final pass cleared the text that had already streamed. The stored reply is
 * now what was shown, with rounds kept apart.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
});

it('stores every round that streamed, past the tool-call cap, with the rounds apart', function (): void {
    FynStreamHarness::fake()
        // The CoALA Planner's own call takes the first turn (Loop/Planner.php).
        ->textTurn('')
        ->textThenToolTurn('First.', 'get_tax_information', ['topic' => 'income_tax'], 'toolu_1')
        ->textThenToolTurn('Second.', 'get_tax_information', ['topic' => 'income_tax'], 'toolu_2')
        ->textThenToolTurn('Third.', 'get_tax_information', ['topic' => 'income_tax'], 'toolu_3')
        ->textTurn('Done.')
        ->bind();
    $conversation = AiConversation::create(['user_id' => $this->user->id, 'title' => 'Rounds', 'status' => 'active', 'model_used' => '']);

    $stream = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'Look through my numbers for me'])
        ->assertOk()->streamedContent();

    $streamed = collect(preg_split('/\n\n/', $stream))
        ->map(fn (string $frame): ?array => str_starts_with(trim($frame), 'data:') ? json_decode(trim(substr(trim($frame), 5)), true) : null)
        ->filter(fn (?array $event): bool => ($event['type'] ?? null) === 'content')
        ->pluck('text')
        ->implode('');
    $stored = $conversation->messages()->where('role', 'assistant')->latest('id')->first()->content;

    expect($stored)->toBe('First. Second. Third. Done.')
        ->and($streamed)->toBe($stored);
});
