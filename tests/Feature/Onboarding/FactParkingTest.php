<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingFactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M19 — OnboardingFactExtractor parks structured facts from free-text
 * messages into ai_conversations.onboarding_parked_facts. The parked
 * shape is bucketed by domain (personal / spouse / dependants / employment /
 * expenditure) so the director can drive targeted hydration later.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('parks a DOB and marital_status volunteered mid-conversation', function () {
    $user = User::factory()->create(['first_name' => 'Chris']);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Test',
    ]);

    app(OnboardingFactExtractor::class)->extractAndPark(
        $conversation,
        'My DOB is 12 March 1985 and I am married.'
    );

    $conversation->refresh();
    $parked = $conversation->onboarding_parked_facts ?? [];

    expect($parked)->toHaveKey('personal');
    expect($parked['personal']['date_of_birth'] ?? null)->toBe('1985-03-12');
    expect($parked['personal']['marital_status'] ?? null)->toBe('married');
});

it('parks spouse first_name from a wife/married-to phrasing', function () {
    $user = User::factory()->create();

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Test',
    ]);

    app(OnboardingFactExtractor::class)->extractAndPark(
        $conversation,
        'I am married to Angela.'
    );

    $conversation->refresh();
    $parked = $conversation->onboarding_parked_facts ?? [];

    expect($parked)->toHaveKey('personal');
    expect($parked['personal']['marital_status'])->toBe('married');

    expect($parked)->toHaveKey('spouse');
    expect($parked['spouse']['first_name'])->toBe('Angela');
});

it('merges later messages into existing parked facts without overwriting earlier ones', function () {
    $user = User::factory()->create();

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Test',
        'onboarding_parked_facts' => [
            'personal' => ['date_of_birth' => '1985-03-12'],
        ],
    ]);

    app(OnboardingFactExtractor::class)->extractAndPark(
        $conversation,
        'I am married.'
    );

    $conversation->refresh();
    $parked = $conversation->onboarding_parked_facts ?? [];

    // Earlier fact preserved.
    expect($parked['personal']['date_of_birth'])->toBe('1985-03-12');
    // New fact merged in.
    expect($parked['personal']['marital_status'])->toBe('married');
});
