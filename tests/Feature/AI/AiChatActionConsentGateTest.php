<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * F13 — the action stream carries the same ai_chat consent gate as its three
 * siblings, and ai_chat cannot be withdrawn through the GDPR consents endpoint.
 */
it('refuses an action stream without ai_chat consent', function () {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->postJson("/api/ai-chat/conversations/{$conversation->id}/action", ['action' => 'resume'])
        ->assertStatus(403)
        ->assertJson(['error' => 'consent_required', 'required' => 'ai_chat']);
});

it('ignores an ai_chat withdrawal sent to the GDPR consents endpoint', function () {
    $user = User::factory()->create();
    UserConsent::create([
        'user_id' => $user->id,
        'consent_type' => UserConsent::TYPE_AI_CHAT,
        'consented' => true,
        'version' => UserConsent::CURRENT_VERSIONS[UserConsent::TYPE_AI_CHAT],
        'consented_at' => now(),
    ]);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/gdpr/consents', ['consents' => [UserConsent::TYPE_AI_CHAT => false]])->assertOk();

    expect(UserConsent::hasConsent($user->id, UserConsent::TYPE_AI_CHAT))->toBeTrue();
});

it('keeps the ai_chat consent version where every existing user consented', function () {
    // hasConsent is version-pinned; bumping this without a re-consent surface
    // would 403 every existing user out of chat (F13). Change deliberately.
    expect(UserConsent::CURRENT_VERSIONS[UserConsent::TYPE_AI_CHAT])->toBe('v1.0');
});
