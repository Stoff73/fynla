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
 * Walked 2026-10-03 (conversation 297): one round's text ran straight into the
 * next on screen ("…recorded properly.I'll pass this…"). Rounds are now kept
 * apart on the stream. Past the tool-call cap the stored reply is the final
 * pass only, by design (7731abcb1, the repetition guard).
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($this->user);
});

it('keeps rounds apart on the stream, and stores the final pass past the tool-call cap', function (): void {
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
    $done = collect(preg_split('/\n\n/', $stream))
        ->map(fn (string $frame): ?array => str_starts_with(trim($frame), 'data:') ? json_decode(trim(substr(trim($frame), 5)), true) : null)
        ->first(fn (?array $event): bool => ($event['type'] ?? null) === 'done');

    // The screen replaces the streamed text with `done.content`, the stored
    // reply, so it shows what a reload shows (CSJ 2026-10-04).
    expect($streamed)->toBe('First. Second. Third. Done.')
        ->and($stored)->toBe('Done.')
        ->and($done['content'] ?? null)->toBe($stored);
});
