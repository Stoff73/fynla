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
 * T8 walk (csjones, Alex 502, #22840). "Delete my round-the-world trip goal"
 * with no goals saved: the capture model guessed goal 0, and the saved reply
 * read "I couldn't save that — There's no saved goal with that id, so nothing
 * was deleted. Give me the missing detail and I will try again." A delete
 * that finds nothing says so in its own words.
 */
it('says the user has no such record instead of a save failure', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($user);
    $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Chat', 'status' => 'active', 'model_used' => '']);

    // The write classifier routes "Delete my …" straight to capture, so the
    // first model call is the capture turn's.
    FynStreamHarness::fake()
        ->toolTurn('delete_record', ['entity_type' => 'goal', 'entity_id' => 0], 'toolu_x')
        ->textTurn("I couldn't delete that goal.")
        ->bind();

    $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'Delete my round-the-world trip goal'])
        ->assertOk()->streamedContent();

    $reply = $conversation->messages()->where('role', 'assistant')->latest('id')->value('content');

    expect($reply)->toContain("You don't have a goal saved, so there's nothing to delete.")
        ->and($reply)->not->toContain("couldn't save")
        ->and($reply)->not->toContain('missing detail');
});
