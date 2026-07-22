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

    public function dailyBackstopHit(User $u): bool
    {
        return $this->isDailyBackstopExceeded($u);
    }

    public function maxTokens(User $u): int
    {
        return $this->getAiMaxTokens($u);
    }

    public function softDegradeModel(): string
    {
        return self::SOFT_DEGRADE_MODEL;
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

it('reads the weekly budget from the tier store, not the legacy plan array', function () use ($harness) {
    $u = User::factory()->create(['tier' => 'free']);
    // free weekly budget = 100k; 7 days summing to 90k → not exceeded
    foreach (range(0, 6) as $d) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($d)->toDateString(), 'tokens_used' => 12_000]);
    }
    expect($harness()->weeklyExceeded($u))->toBeFalse(); // 84k < 100k
});

it('soft-degrades to the Anthropic cheap model under the anthropic provider', function () use ($harness) {
    config(['services.ai_provider' => 'anthropic', 'services.anthropic.chat_model' => null]); // let the trait choose
    $u = User::factory()->create(['tier' => 'free']);
    foreach (range(0, 6) as $d) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($d)->toDateString(), 'tokens_used' => 30_000]);
    } // 210k > 100k weekly
    $h = $harness();
    $degraded = $h->model($u, 'complex');
    expect($h->weeklyExceeded($u))->toBeTrue()
        ->and($degraded)->toBe($h->softDegradeModel())
        ->and($degraded)->toBe('claude-haiku-4-5-20251001'); // literal anchor — catches a constant-value regression
});

it('soft-degrades to a valid xAI model under the xai provider (never an Anthropic model)', function () use ($harness) {
    // Regression guard: the soft-degrade model MUST be provider-appropriate.
    // Returning the Anthropic SOFT_DEGRADE_MODEL under AI_PROVIDER=xai sends an
    // invalid model name to the xAI endpoint and BREAKS chat instead of
    // degrading it. The degrade must stay on the xAI side so chat stays open.
    config(['services.ai_provider' => 'xai', 'services.xai.chat_model' => null, 'services.xai.degrade_chat_model' => null]);
    $u = User::factory()->create(['tier' => 'free']);
    foreach (range(0, 6) as $d) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($d)->toDateString(), 'tokens_used' => 30_000]);
    } // 210k > 100k weekly
    $h = $harness();
    $degraded = $h->model($u, 'complex');
    expect($h->weeklyExceeded($u))->toBeTrue()
        ->and($degraded)->toBe('grok-4.3') // DEFAULT_MODEL_XAI — keeps chat open on xAI
        ->and($degraded)->not->toBe($h->softDegradeModel()); // never the Anthropic model
});

it('never meters or soft-degrades preview personas, regardless of usage', function () use ($harness) {
    config([
        'services.ai_provider' => 'anthropic',
        'services.anthropic.chat_model' => 'claude-sonnet-4-6-20260320',
    ]);
    $u = User::factory()->create(['is_preview_user' => true, 'tier' => null]);
    // Far exceeds any weekly budget and any daily backstop.
    foreach (range(0, 6) as $d) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($d)->toDateString(), 'tokens_used' => 500_000]);
    }
    $h = $harness();
    // Both gates short-circuit on is_preview_user before any tier-store call,
    // so the soft-degrade branch in getAiModel() is never reached for a
    // preview persona — proven by weeklyExceeded() / dailyBackstopHit being
    // false despite usage that would trip every real tier, and by the
    // returned model NOT being the soft-degrade model.
    expect($h->weeklyExceeded($u))->toBeFalse()
        ->and($h->dailyBackstopHit($u))->toBeFalse()
        ->and($h->model($u, 'complex'))->toBe('claude-sonnet-4-6-20260320')
        ->and($h->model($u, 'complex'))->not->toBe($h->softDegradeModel());
});

it('the daily hard backstop only trips at the abuse ceiling, not the weekly number', function () use ($harness) {
    $u = User::factory()->create(['tier' => 'free']); // daily backstop 500k
    AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->toDateString(), 'tokens_used' => 120_000]);
    expect($harness()->dailyBackstopHit($u))->toBeFalse(); // over weekly-pace but below abuse
    AiDailyUsage::where('user_id', $u->id)->update(['tokens_used' => 600_000]);
    expect($harness()->dailyBackstopHit($u))->toBeTrue();
});
