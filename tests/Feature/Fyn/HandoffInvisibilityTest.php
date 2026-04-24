<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('zero persona_state_change SSE events emitted during handoff', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    $response = $this->actingAs($user)
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", [
            'message' => 'I want advice — oh actually add Aviva life £300k',
        ]);

    $raw = $response->streamedContent();
    $events = collect(explode("\n\n", $raw))
        ->filter(fn ($c) => str_starts_with(trim($c), 'data:'))
        ->map(fn ($c) => json_decode(preg_replace('/^data:\s*/', '', trim($c)), true))
        ->filter();

    expect($events->pluck('type')->filter(fn ($t) => $t === 'persona_state_change'))->toBeEmpty();
});
