<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiDailyUsage;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Sprint 0.11.1 — Atomic token-budget table.
 *
 * Replaces the 5-minute Cache::remember layer that gated the daily token
 * budget. The race window allowed two parallel chat() calls for the same
 * user to both pass `hasTokenBudget` (cached value) before either
 * invalidated the cache. With the new table, every read goes straight
 * to MySQL and every write happens inside a DB::transaction with
 * `SELECT ... FOR UPDATE` — concurrent writes serialise on the row
 * lock, so the daily total cannot be lost or overwritten.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function callRecordTokenUsage(User $user, int $tokens): void
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'recordTokenUsage');
    $reflection->setAccessible(true);
    $reflection->invoke($agent, $user, $tokens);
}

function callGetTodayTokenUsage(User $user): int
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'getTodayTokenUsage');
    $reflection->setAccessible(true);

    return (int) $reflection->invoke($agent, $user);
}

function callHasTokenBudget(User $user): bool
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'hasTokenBudget');
    $reflection->setAccessible(true);

    return (bool) $reflection->invoke($agent, $user);
}

describe('Atomic token budget — recordTokenUsage (S0.11.1)', function () {
    it('creates a new ai_daily_usage row when none exists for today', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        callRecordTokenUsage($user, 1500);

        $row = AiDailyUsage::where('user_id', $user->id)->first();
        expect($row)->not->toBeNull()
            ->and((int) $row->tokens_used)->toBe(1500)
            ->and($row->usage_date->toDateString())->toBe(now()->toDateString());
    });

    it('increments the existing row on subsequent calls', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        callRecordTokenUsage($user, 500);
        callRecordTokenUsage($user, 700);
        callRecordTokenUsage($user, 200);

        expect(callGetTodayTokenUsage($user))->toBe(1400);
    });

    it('runs inside a DB::transaction with FOR UPDATE on the row', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        $sqlTrace = [];
        DB::listen(function ($query) use (&$sqlTrace) {
            $sqlTrace[] = strtolower($query->sql);
        });

        callRecordTokenUsage($user, 100);

        $hasForUpdate = collect($sqlTrace)->contains(fn (string $sql) => str_contains($sql, 'for update'));
        expect($hasForUpdate)->toBeTrue();
    });

    it('is a no-op when tokens is zero or negative', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        callRecordTokenUsage($user, 0);
        callRecordTokenUsage($user, -50);

        expect(AiDailyUsage::where('user_id', $user->id)->count())->toBe(0)
            ->and(callGetTodayTokenUsage($user))->toBe(0);
    });

    it('isolates per-user totals', function () {
        $alice = User::factory()->create(['is_preview_user' => false]);
        $bob = User::factory()->create(['is_preview_user' => false]);

        callRecordTokenUsage($alice, 1000);
        callRecordTokenUsage($bob, 2000);
        callRecordTokenUsage($alice, 500);

        expect(callGetTodayTokenUsage($alice))->toBe(1500)
            ->and(callGetTodayTokenUsage($bob))->toBe(2000);
    });
});

describe('Atomic token budget — getTodayTokenUsage / hasTokenBudget (S0.11.1)', function () {
    it('reads directly from ai_daily_usage with no cache layer', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        // Pre-S0.11.1 the trait cached the result for 5 minutes via
        // `ai_daily_tokens_{id}_{date}`. Pin that the cache key is no
        // longer consulted on read — a stale cache value must NOT win
        // over the live DB row.
        $cacheKey = "ai_daily_tokens_{$user->id}_".now()->format('Y-m-d');
        Cache::put($cacheKey, 99999, 300);

        AiDailyUsage::create([
            'user_id' => $user->id,
            'usage_date' => now()->toDateString(),
            'tokens_used' => 250,
        ]);

        expect(callGetTodayTokenUsage($user))->toBe(250);
    });

    it('returns 0 when no usage row exists for today', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        expect(callGetTodayTokenUsage($user))->toBe(0);
    });

    it('does not count yesterday usage toward today', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        AiDailyUsage::create([
            'user_id' => $user->id,
            'usage_date' => now()->subDay()->toDateString(),
            'tokens_used' => 50000,
        ]);

        expect(callGetTodayTokenUsage($user))->toBe(0);
    });

    it('hasTokenBudget returns true while under the daily limit', function () {
        $user = User::factory()->create(['is_preview_user' => true]); // preview = 100k

        AiDailyUsage::create([
            'user_id' => $user->id,
            'usage_date' => now()->toDateString(),
            'tokens_used' => 50000,
        ]);

        expect(callHasTokenBudget($user))->toBeTrue();
    });

    it('hasTokenBudget returns false at the daily limit', function () {
        $user = User::factory()->create(['is_preview_user' => true]);

        AiDailyUsage::create([
            'user_id' => $user->id,
            'usage_date' => now()->toDateString(),
            'tokens_used' => 100000,
        ]);

        expect(callHasTokenBudget($user))->toBeFalse();
    });
});

describe('Atomic token budget — concurrency (S0.11.1)', function () {
    it('two sequential record calls at the boundary preserve both writes', function () {
        $user = User::factory()->create(['is_preview_user' => true]);

        // Pre-load to just below the preview limit (100k).
        AiDailyUsage::create([
            'user_id' => $user->id,
            'usage_date' => now()->toDateString(),
            'tokens_used' => 95000,
        ]);

        // Two consecutive consumes — both writes survive (no lost update),
        // total ends at 95k + 3k + 3k = 101k. The next hasTokenBudget
        // check sees the user is over their cap.
        callRecordTokenUsage($user, 3000);
        callRecordTokenUsage($user, 3000);

        expect(callGetTodayTokenUsage($user))->toBe(101000)
            ->and(callHasTokenBudget($user))->toBeFalse();
    });

    it('a second request at the boundary sees token_limit on hasTokenBudget', function () {
        $user = User::factory()->create(['is_preview_user' => true]);

        // Request A burns the entire budget.
        callRecordTokenUsage($user, 100000);

        // Request B starts fresh — the new ai_daily_usage row is
        // immediately visible on the next hasTokenBudget call (no 5min
        // cache window). Pre-S0.11.1 this could return true because the
        // cache had not yet been invalidated.
        expect(callHasTokenBudget($user))->toBeFalse();
    });

    it('rolls back the row write when the wrapping transaction aborts', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        try {
            DB::transaction(function () use ($user) {
                callRecordTokenUsage($user, 1000);
                throw new RuntimeException('forced rollback');
            });
        } catch (RuntimeException $e) {
            // expected
        }

        expect(AiDailyUsage::where('user_id', $user->id)->count())->toBe(0);
    });
});
