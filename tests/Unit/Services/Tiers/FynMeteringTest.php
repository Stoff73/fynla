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

    public function softDegradeModel(): string
    {
        return self::SOFT_DEGRADE_MODEL;
    }
};

it('reads the weekly budget from the tier store, not the legacy plan array', function () use ($harness) {
    $u = User::factory()->create(['tier' => 'free']);
    // free weekly budget = 100k; 7 days summing to 90k → not exceeded
    foreach (range(0, 6) as $d) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($d)->toDateString(), 'tokens_used' => 12_000]);
    }
    expect($harness()->weeklyExceeded($u))->toBeFalse(); // 84k < 100k
});

it('soft-degrades the model when the weekly budget is exceeded', function () use ($harness) {
    config(['services.anthropic.chat_model' => null]); // let the trait choose
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

it('never meters or soft-degrades preview personas, regardless of usage', function () use ($harness) {
    config(['services.anthropic.chat_model' => null]); // let the trait choose
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
        ->and($h->model($u, 'complex'))->not->toBe($h->softDegradeModel());
});

it('the daily hard backstop only trips at the abuse ceiling, not the weekly number', function () use ($harness) {
    $u = User::factory()->create(['tier' => 'free']); // daily backstop 500k
    AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->toDateString(), 'tokens_used' => 120_000]);
    expect($harness()->dailyBackstopHit($u))->toBeFalse(); // over weekly-pace but below abuse
    AiDailyUsage::where('user_id', $u->id)->update(['tokens_used' => 600_000]);
    expect($harness()->dailyBackstopHit($u))->toBeTrue();
});
