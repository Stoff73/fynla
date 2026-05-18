<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Fyn\FynSystemPrompt;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('legacy mode still uses AdvicePromptBuilder output', function (): void {
    config()->set('fyn.prompt_architecture', 'legacy');
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $agent = app(CoordinatingAgent::class);

    $prompt = (fn () => $this->buildSystemPrompt($user, null, null, null, $conversation))->call($agent);

    expect($prompt)->toContain('<user_profile>'); // legacy interleaves profile into system
});

it('unified mode returns the static system prompt verbatim', function (): void {
    config()->set('fyn.prompt_architecture', 'unified');
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $agent = app(CoordinatingAgent::class);

    $prompt = (fn () => $this->buildSystemPrompt($user, null, null, null, $conversation))->call($agent);

    expect($prompt)->toBe(FynSystemPrompt::text());
});
