<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Loop\ResumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * CoALA Phase 5 item 7 (FR-M9) — the resumption surface: flag / read / clear and
 * the GET /resumption + DELETE endpoints.
 */
function resumptionConversation(User $user): AiConversation
{
    return AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Resumption',
        'message_count' => 0,
        'last_message_at' => now(),
    ]);
}

it('flags, reads, finds and clears a resumption via the service', function () {
    $user = User::factory()->create();
    $conversation = resumptionConversation($user);
    $svc = app(ResumptionService::class);

    expect($svc->pending($conversation))->toBeNull()
        ->and($svc->latestForUser($user->id))->toBeNull();

    $svc->flag($conversation, 'fatal_error', 'pick up where we left off?');

    expect($svc->pending($conversation->fresh())['reason'])->toBe('fatal_error')
        ->and($svc->latestForUser($user->id)->id)->toBe($conversation->id);

    $svc->clear($conversation->fresh());

    expect($svc->pending($conversation->fresh()))->toBeNull()
        ->and($svc->latestForUser($user->id))->toBeNull();
});

it('GET /resumption returns the pending resumption', function () {
    $user = User::factory()->create();
    $conversation = resumptionConversation($user);
    app(ResumptionService::class)->flag($conversation, 'fatal_error', 'pick up?');
    Sanctum::actingAs($user);

    $this->getJson('/api/ai-chat/resumption')
        ->assertOk()
        ->assertJsonPath('data.has_pending', true)
        ->assertJsonPath('data.conversation_id', $conversation->id)
        ->assertJsonPath('data.message', 'pick up?');
});

it('GET /resumption returns has_pending false when there is none', function () {
    $user = User::factory()->create();
    resumptionConversation($user);
    Sanctum::actingAs($user);

    $this->getJson('/api/ai-chat/resumption')
        ->assertOk()
        ->assertJsonPath('data.has_pending', false);
});

it('DELETE /conversations/{id}/resumption clears it (continue or start fresh)', function () {
    $user = User::factory()->create();
    $conversation = resumptionConversation($user);
    app(ResumptionService::class)->flag($conversation, 'fatal_error', 'pick up?');
    Sanctum::actingAs($user);

    $this->deleteJson("/api/ai-chat/conversations/{$conversation->id}/resumption")->assertOk();

    expect(app(ResumptionService::class)->pending($conversation->fresh()))->toBeNull();
});
