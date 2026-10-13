<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiDailyUsage;
use App\Models\TierConfiguration;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

/*
 * T48 (CSJ 2026-10-12): one Fyn limit per tier, per rolling week.
 * Free 750,000 tokens, Premium 3,000,000. Over the limit, Fyn stops with
 * "You've reached your weekly Fyn usage limit."; the daily stop and the
 * cheaper-model switch are gone.
 */

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

function t48Turn(User $user, string $message = 'What is my net worth?'): array
{
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'T48',
        'message_count' => 0,
    ]);

    return iterator_to_array(
        app(CoordinatingAgent::class)->chatWithPromptOverride($user, $conversation, $message, null, 'You are Fyn.', null),
        preserve_keys: false,
    );
}

it('holds one weekly limit per tier: Free 750,000, Premium 3,000,000', function () {
    expect(Schema::hasColumn('tier_configurations', 'fyn_daily_hard_backstop'))->toBeFalse()
        ->and(TierConfiguration::where('tier', 'free')->value('fyn_weekly_token_budget'))->toBe(750_000)
        ->and(TierConfiguration::where('tier', 'premium')->value('fyn_weekly_token_budget'))->toBe(3_000_000);
});

it('stops the turn with the weekly message once the rolling week reaches the limit', function () {
    $user = User::factory()->create(['tier' => 'free', 'is_preview_user' => false, 'onboarding_completed' => true]);
    AiDailyUsage::create(['user_id' => $user->id, 'usage_date' => now()->subDays(6)->toDateString(), 'tokens_used' => 400_000]);
    AiDailyUsage::create(['user_id' => $user->id, 'usage_date' => now()->toDateString(), 'tokens_used' => 350_000]);

    $events = t48Turn($user);
    $limit = collect($events)->firstWhere('type', 'token_limit');

    expect($limit)->not->toBeNull()
        ->and($limit['message'])->toBe("You've reached your weekly Fyn usage limit.")
        ->and($limit['reset_at'])->toBe(now()->addDay()->startOfDay()->toIso8601String());
});

it('answers a Free user who has used more than the old daily stop today but is under the week', function () {
    $user = User::factory()->create(['tier' => 'free', 'is_preview_user' => false, 'onboarding_completed' => true]);
    AiDailyUsage::create(['user_id' => $user->id, 'usage_date' => now()->toDateString(), 'tokens_used' => 600_000]);
    FynStreamHarness::fake()->textTurn('Your net worth is shown on your dashboard.')->bind();

    $events = t48Turn($user);

    expect(collect($events)->firstWhere('type', 'token_limit'))->toBeNull()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(''))
        ->toContain('Your net worth is shown on your dashboard.');
});
