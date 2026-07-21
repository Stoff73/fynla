<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function interruptionUser(string $step = OnboardingStateMachine::STATE_PATH_CHOICE): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => $step,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);

    return [$user, $conversation];
}

function driveDirector(User $user, AiConversation $conversation, string $message): array
{
    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, $message) as $event) {
        $received[] = $event;
    }

    return $received;
}

it('still emits the plain retry for unclassifiable free text', function () {
    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'asdf qwerty');

    $texts = collect($received)->where('type', 'content')->pluck('text');
    expect($texts->first(fn ($t) => str_contains($t, "didn't catch that")))->not->toBeNull();
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});
