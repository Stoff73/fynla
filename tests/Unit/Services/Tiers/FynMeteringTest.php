<?php

declare(strict_types=1);

use App\Models\AiDailyUsage;
use App\Models\User;
use App\Traits\HasAiGuardrails;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(fn () => $this->seed(TierConfigurationSeeder::class));

// Use a tiny harness exposing the protected trait methods.
$harness = fn () => new class
{
    use HasAiGuardrails;

    public function model(User $u, string $c = 'standard'): string
    {
        return $this->getAiModel($u, $c);
    }

    public function weeklyExceeded(User $u): bool
    {
        return $this->isWeeklyBudgetExceeded($u);
    }

    public function hasBudget(User $u): bool
    {
        return $this->hasTokenBudget($u);
    }

    public function maxTokens(User $u): int
    {
        return $this->getAiMaxTokens($u);
    }
};

$useTokens = function (User $u, array $byDaysAgo): void {
    foreach ($byDaysAgo as $daysAgo => $tokens) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($daysAgo)->toDateString(), 'tokens_used' => $tokens]);
    }
};

it('gives Premium the advanced model for complex requests and the paid output allowance', function () use ($harness) {
    config([
        'services.ai_provider' => 'anthropic',
        'services.anthropic.chat_model' => 'standard-model',
        'services.anthropic.advanced_chat_model' => 'advanced-model',
    ]);
    $premium = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium', 'plan' => 'free']);

    expect($harness()->model($premium, 'complex'))->toBe('advanced-model')
        ->and($harness()->maxTokens($premium))->toBe(8192);
});

it('keeps Free on the standard model and lower output allowance', function () use ($harness) {
    config([
        'services.ai_provider' => 'anthropic',
        'services.anthropic.chat_model' => 'standard-model',
        'services.anthropic.advanced_chat_model' => 'advanced-model',
    ]);
    $free = User::factory()->create(['tier' => 'free', 'plan' => 'premium']);

    expect($harness()->model($free, 'complex'))->toBe('standard-model')
        ->and($harness()->maxTokens($free))->toBe(4096);
});

it('stops Free at 750,000 tokens over the rolling week (CSJ 2026-10-12)', function () use ($harness, $useTokens) {
    $u = User::factory()->create(['tier' => 'free']);
    $useTokens($u, [0 => 100_000, 1 => 100_000, 2 => 100_000, 3 => 100_000, 4 => 100_000, 5 => 100_000, 6 => 149_999]);
    expect($harness()->hasBudget($u))->toBeTrue(); // 749,999

    AiDailyUsage::where('user_id', $u->id)->whereDate('usage_date', now()->toDateString())->update(['tokens_used' => 100_001]);
    expect($harness()->weeklyExceeded($u))->toBeTrue()
        ->and($harness()->hasBudget($u))->toBeFalse(); // 750,000
});

it('stops Premium at 3,000,000 tokens over the rolling week', function () use ($harness, $useTokens) {
    $u = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $useTokens($u, [0 => 2_999_999]);
    expect($harness()->hasBudget($u))->toBeTrue();

    $useTokens($u, [3 => 1]);
    expect($harness()->hasBudget($u))->toBeFalse();
});

it('does not count usage older than the rolling week', function () use ($harness, $useTokens) {
    $u = User::factory()->create(['tier' => 'free']);
    $useTokens($u, [7 => 750_000, 6 => 10_000]);

    expect($harness()->hasBudget($u))->toBeTrue();
});

it('has no daily stop: one day above the old 500,000 backstop is still within the week', function () use ($harness, $useTokens) {
    $u = User::factory()->create(['tier' => 'free']);
    $useTokens($u, [0 => 600_000]);

    expect($harness()->hasBudget($u))->toBeTrue();
});

it('never switches to a cheaper model, whatever the week has used', function () use ($harness, $useTokens) {
    config(['services.ai_provider' => 'anthropic', 'services.anthropic.chat_model' => 'standard-model']);
    $u = User::factory()->create(['tier' => 'free']);
    $useTokens($u, [0 => 900_000]);

    expect($harness()->model($u, 'complex'))->toBe('standard-model');
});

it('never meters preview personas, regardless of usage', function () use ($harness, $useTokens) {
    config([
        'services.ai_provider' => 'anthropic',
        'services.anthropic.chat_model' => 'claude-sonnet-4-6-20260320',
    ]);
    $u = User::factory()->create(['is_preview_user' => true, 'tier' => null]);
    $useTokens($u, [0 => 5_000_000, 1 => 5_000_000]);

    $h = $harness();
    expect($h->weeklyExceeded($u))->toBeFalse()
        ->and($h->hasBudget($u))->toBeTrue()
        ->and($h->model($u, 'complex'))->toBe('claude-sonnet-4-6-20260320')
        ->and($h->getTokenUsageDetails($u)['has_budget'])->toBeTrue();
});

describe('getTokenUsageDetails reports the rolling week', function () use ($harness, $useTokens) {
    it('reports the week total against the weekly limit', function () use ($harness, $useTokens) {
        $u = User::factory()->create(['tier' => 'free']);
        $useTokens($u, [0 => 100_000, 3 => 200_000, 8 => 400_000]);

        expect($harness()->getTokenUsageDetails($u))->toMatchArray([
            'used' => 300_000,
            'limit' => 750_000,
            'remaining' => 450_000,
            'percent_used' => 40.0,
            'has_budget' => true,
        ]);
    });

    it('resets when enough old days leave the week to bring the total under the limit', function () use ($harness, $useTokens) {
        $this->travelTo(now()->setTime(15, 0));
        $u = User::factory()->create(['tier' => 'free']);
        // 6 days ago 400k + today 400k = 800k. At midnight tonight the
        // 6-days-ago row leaves the window: 400k < 750k.
        $useTokens($u, [6 => 400_000, 0 => 400_000]);

        $details = $harness()->getTokenUsageDetails($u);

        expect($details['has_budget'])->toBeFalse()
            ->and($details['reset_at'])->toBe(now()->addDay()->startOfDay()->toIso8601String())
            ->and($details['seconds_until_reset'])->toBe(9 * 3600);
    });

    it('waits for today to leave the week when today alone is over the limit', function () use ($harness, $useTokens) {
        $this->travelTo(now()->setTime(15, 0));
        $u = User::factory()->create(['tier' => 'free']);
        $useTokens($u, [0 => 800_000]);

        expect($harness()->getTokenUsageDetails($u)['reset_at'])
            ->toBe(now()->addDays(7)->startOfDay()->toIso8601String());
    });

    it('resets on the day the running total first drops under the limit', function () use ($harness, $useTokens) {
        $this->travelTo(now()->setTime(15, 0));
        $u = User::factory()->create(['tier' => 'free']);
        // 5 days ago 300k, 2 days ago 300k, today 300k = 900k.
        // In 2 days the 5-days-ago row leaves: 600k < 750k.
        $useTokens($u, [5 => 300_000, 2 => 300_000, 0 => 300_000]);

        expect($harness()->getTokenUsageDetails($u)['reset_at'])
            ->toBe(now()->addDays(2)->startOfDay()->toIso8601String());
    });
});
