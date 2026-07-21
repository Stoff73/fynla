<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynSystemPrompt;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('unified onboarding turn uses the static prompt + sets focus on the agent', function (): void {
    config()->set('fyn.prompt_architecture', 'unified');
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_selection' => 'savings',
    ]);

    // The director's restricted-prompt builder must return the static
    // prompt and pass the focus through. Assert via the seam helper the
    // director uses to compute the override (extracted in Step 3).
    $director = app(OnboardingChatDirector::class);
    [$prompt, $focus] = (fn () => $this->resolveUnifiedRestrictedPrompt($user, 'savings'))
        ->call($director);

    expect($prompt)->toBe(FynSystemPrompt::text())
        ->and($focus)->toBe('savings');
});
