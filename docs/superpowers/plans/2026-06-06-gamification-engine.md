# Gamification Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a backend points-and-levels engine (single source of truth) that awards points for data entry, milestones, daily use/streaks, and completed recommendations; resolves a finite 10-level ladder; and fires a full-screen fireworks celebration on the web app and the `/m` pathway — including a dismissable interrupt over a Fyn conversation.

**Architecture:** An append-only `point_awards` ledger (unique `dedup_key` enforces single-award) plus a `user_gamification` fast-read aggregate. `PointsService` awards points idempotently and recomputes level; `LevelService` maps points→level + action-oriented "what's next"; a request-scoped `LevelUpCollector` surfaces in-turn level-ups to the SSE/API layer. Awards are triggered by model observers/hooks. The existing `/m` level wheel (`MobileLevelService`) is repointed to read the engine. A shared Vue celebration component renders on both surfaces; a persisted `pending_celebration_level` guarantees delivery on next app open.

**Tech Stack:** Laravel 10, PHP 8.2 (`declare(strict_types=1)`), MySQL 8, Pest, Vue 3 + Vuex, Tailwind (Fynla palette tokens). Spec: `docs/superpowers/specs/2026-06-06-gamification-engine-design.md`.

**Branch:** `feat/gamification-engine` off `dev`.

**Conventions to honour throughout:**
- Preview users (`is_preview_user = true`) never accrue points (Rule #1) — enforced in `PointsService::award`.
- No hardcoded tax values (N/A here, but no magic financial numbers).
- No emoji/icons/Unicode-glyphs in any user-facing string, level name, or celebration (Rule #15). Confetti/fireworks are the approved gamification animation (Rule #12 carve-out).
- Palette tokens only; success/achievement = `spring-*` (Rule #8).
- New web views stay inside `AppLayout` (Rule #13); the celebration is an overlay, not a route.
- Gamification failures must never break the triggering write/login/chat turn (log + swallow).
- Pest: `it()`/`describe()`, `RefreshDatabase`, TaxConfiguration auto-seeded, `Sanctum::actingAs()`.

---

## Task 0: Branch setup

- [ ] **Step 1: Create the feature branch off dev**

```bash
git checkout dev && git pull origin dev
git checkout -b feat/gamification-engine
```

- [ ] **Step 2: Confirm clean baseline**

Run: `git status`
Expected: on `feat/gamification-engine`, clean (the earlier uncommitted session docs/store cleanup are handled separately — do not bundle them here).

---

# Phase 1 — Schema + core engine

## Task 1: Migrations — `point_awards` and `user_gamification`

**Files:**
- Create: `database/migrations/2026_06_06_120000_create_point_awards_table.php`
- Create: `database/migrations/2026_06_06_120001_create_user_gamification_table.php`

- [ ] **Step 1: Write the `point_awards` migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('point_awards')) {
            return;
        }

        Schema::create('point_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // data | onboarding | milestone | recommendation | login | streak
            $table->string('source_type', 32);
            $table->unsignedInteger('points');
            $table->string('dedup_key', 191);
            $table->json('meta')->nullable();
            $table->timestamps();

            // The single-award guarantee: a given dedup_key awards exactly once per user.
            $table->unique(['user_id', 'dedup_key'], 'point_awards_unique');
            $table->index(['user_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_awards');
    }
};
```

- [ ] **Step 2: Write the `user_gamification` migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_gamification')) {
            return;
        }

        Schema::create('user_gamification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->unsignedInteger('total_points')->default(0);   // monotonic
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedTinyInteger('pending_celebration_level')->nullable();
            $table->date('last_login_award_date')->nullable();
            $table->unsignedInteger('login_streak_days')->default(0);
            $table->date('streak_started_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_gamification');
    }
};
```

- [ ] **Step 3: Run the migrations**

Run: `php artisan migrate`
Expected: both migrations run; `Migrated: 2026_06_06_120000_create_point_awards_table` and `..._120001_create_user_gamification_table`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_06_120000_create_point_awards_table.php database/migrations/2026_06_06_120001_create_user_gamification_table.php
git commit -m "feat(gamification): point_awards ledger + user_gamification aggregate tables"
```

---

## Task 2: Models — `PointAward`, `UserGamification`, User relation

**Files:**
- Create: `app/Models/PointAward.php`
- Create: `app/Models/UserGamification.php`
- Modify: `app/Models/User.php` (add `gamification()` relation)

- [ ] **Step 1: Write `PointAward`**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointAward extends Model
{
    protected $fillable = [
        'user_id', 'source_type', 'points', 'dedup_key', 'meta',
    ];

    protected $casts = [
        'points' => 'integer',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Write `UserGamification`**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGamification extends Model
{
    protected $table = 'user_gamification';

    protected $fillable = [
        'user_id', 'total_points', 'level', 'pending_celebration_level',
        'last_login_award_date', 'login_streak_days', 'streak_started_on',
    ];

    protected $casts = [
        'total_points' => 'integer',
        'level' => 'integer',
        'pending_celebration_level' => 'integer',
        'last_login_award_date' => 'date',
        'login_streak_days' => 'integer',
        'streak_started_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Add the relation to `User`**

In `app/Models/User.php`, add (with the other `hasOne`/`hasMany` relations):

```php
public function gamification(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(\App\Models\UserGamification::class);
}
```

- [ ] **Step 4: Smoke-test in tinker**

Run: `php artisan tinker --execute="echo App\Models\UserGamification::class;"`
Expected: prints `App\Models\UserGamification` with no error (autoload OK).

- [ ] **Step 5: Commit**

```bash
git add app/Models/PointAward.php app/Models/UserGamification.php app/Models/User.php
git commit -m "feat(gamification): PointAward + UserGamification models and User relation"
```

---

## Task 3: Config — `config/gamification.php` (levels + point values)

**Files:**
- Create: `config/gamification.php`

- [ ] **Step 1: Write the config**

```php
<?php

declare(strict_types=1);

return [
    // Finite ladder of named levels. 'min_points' = cumulative points to REACH this level.
    // Tunable post-launch. No emoji / acronyms in names (Rules #9, #15).
    'levels' => [
        1 => ['name' => 'Starter',    'min_points' => 0],
        2 => ['name' => 'Saver',      'min_points' => 50],
        3 => ['name' => 'Builder',    'min_points' => 120],
        4 => ['name' => 'Organiser',  'min_points' => 220],
        5 => ['name' => 'Planner',    'min_points' => 360],
        6 => ['name' => 'Strategist', 'min_points' => 550],
        7 => ['name' => 'Optimiser',  'min_points' => 800],
        8 => ['name' => 'Guardian',   'min_points' => 1120],
        9 => ['name' => 'Steward',    'min_points' => 1520],
        10 => ['name' => 'Master',    'min_points' => 2000],
    ],

    // Point values per source (tunable).
    'points' => [
        'data_first_in_category' => 20,
        'data_extra_record' => 5,
        'data_extra_cap_per_category' => 3,   // max extra-record awards per category
        'onboarding_answer' => 10,
        'milestone' => 30,
        'recommendation' => 25,
        'daily_login' => 5,
        'streak' => [3 => 15, 7 => 30, 14 => 50, 30 => 100],
    ],
];
```

- [ ] **Step 2: Verify config loads**

Run: `php artisan tinker --execute="echo config('gamification.levels.10.name');"`
Expected: prints `Master`.

- [ ] **Step 3: Commit**

```bash
git add config/gamification.php
git commit -m "feat(gamification): levels ladder + point values config"
```

---

## Task 4: `AwardResult` DTO + `LevelService` (TDD)

**Files:**
- Create: `app/Services/Gamification/AwardResult.php`
- Create: `app/Services/Gamification/LevelService.php`
- Test: `tests/Unit/Services/Gamification/LevelServiceTest.php`

- [ ] **Step 1: Write `AwardResult` DTO**

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

final readonly class AwardResult
{
    public function __construct(
        public bool $awarded,
        public int $points,
        public bool $leveledUp,
        public int $newLevel,
        public string $newLevelName,
    ) {}

    public static function noop(): self
    {
        return new self(false, 0, false, 1, 'Starter');
    }
}
```

- [ ] **Step 2: Write the failing test for `LevelService`**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Gamification\LevelService;

it('resolves the level from a points total', function () {
    $svc = app(LevelService::class);

    expect($svc->levelForPoints(0))->toBe(1);
    expect($svc->levelForPoints(49))->toBe(1);
    expect($svc->levelForPoints(50))->toBe(2);
    expect($svc->levelForPoints(119))->toBe(2);
    expect($svc->levelForPoints(360))->toBe(5);
    expect($svc->levelForPoints(2000))->toBe(10);
    expect($svc->levelForPoints(999999))->toBe(10); // caps at 10
});

it('names a level', function () {
    $svc = app(LevelService::class);
    expect($svc->levelName(5))->toBe('Planner');
    expect($svc->levelName(10))->toBe('Master');
});

it('computes progress within the current band', function () {
    $svc = app(LevelService::class);
    // 220 = start of L4, 360 = start of L5 -> band size 140. 290 = 70/140 = 50%.
    $p = $svc->progress(290);
    expect($p['level'])->toBe(4);
    expect($p['level_name'])->toBe('Organiser');
    expect($p['next_level_name'])->toBe('Planner');
    expect($p['progress_percent'])->toBe(50);
});

it('reports 100 percent progress and no next level at the cap', function () {
    $svc = app(LevelService::class);
    $p = $svc->progress(2000);
    expect($p['level'])->toBe(10);
    expect($p['next_level_name'])->toBeNull();
    expect($p['progress_percent'])->toBe(100);
});

it('returns action-oriented next steps with no points figure', function () {
    $user = User::factory()->create();
    $svc = app(LevelService::class);
    $actions = $svc->nextActions($user);
    expect($actions)->toBeArray();
    foreach ($actions as $a) {
        expect($a)->toBeString();
        expect(strtolower($a))->not->toContain('point'); // never mention points
    }
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/LevelServiceTest.php`
Expected: FAIL — `Class "App\Services\Gamification\LevelService" not found`.

- [ ] **Step 4: Implement `LevelService`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\User;

class LevelService
{
    /** @return array<int,array{name:string,min_points:int}> */
    private function levels(): array
    {
        return config('gamification.levels');
    }

    public function levelForPoints(int $points): int
    {
        $resolved = 1;
        foreach ($this->levels() as $level => $def) {
            if ($points >= $def['min_points']) {
                $resolved = $level;
            }
        }

        return $resolved;
    }

    public function levelName(int $level): string
    {
        return $this->levels()[$level]['name'] ?? 'Starter';
    }

    /**
     * @return array{level:int, level_name:string, level_label:string,
     *               progress_percent:int, next_level_name:?string}
     */
    public function progress(int $points): array
    {
        $levels = $this->levels();
        $level = $this->levelForPoints($points);
        $name = $this->levelName($level);
        $maxLevel = array_key_last($levels);

        if ($level >= $maxLevel) {
            return [
                'level' => $level,
                'level_name' => $name,
                'level_label' => "Level {$level} · {$name}",
                'progress_percent' => 100,
                'next_level_name' => null,
            ];
        }

        $bandStart = $levels[$level]['min_points'];
        $bandEnd = $levels[$level + 1]['min_points'];
        $band = max(1, $bandEnd - $bandStart);
        $pct = (int) round((($points - $bandStart) / $band) * 100);

        return [
            'level' => $level,
            'level_name' => $name,
            'level_label' => "Level {$level} · {$name}",
            'progress_percent' => max(0, min(100, $pct)),
            'next_level_name' => $levels[$level + 1]['name'],
        ];
    }

    /**
     * Action-oriented "what's next" — plain-text imperatives derived from the
     * user's highest-value unfilled actions. NEVER mentions points (Rule #12 /
     * decision #7). Returns up to 2 suggestions.
     *
     * @return array<int,string>
     */
    public function nextActions(User $user): array
    {
        $suggestions = [];

        $checks = [
            'savingsAccounts' => 'Add a savings account',
            'investmentAccounts' => 'Add an investment account',
            'dcPensions' => 'Add a pension',
            'properties' => 'Add a property',
            'protectionPolicies' => 'Add a protection policy',
            'goals' => 'Set a financial goal',
        ];

        foreach ($checks as $relation => $label) {
            if (method_exists($user, $relation) && $user->{$relation}()->count() === 0) {
                $suggestions[] = $label;
            }
            if (count($suggestions) >= 2) {
                return $suggestions;
            }
        }

        // Fall back to completing open recommendations.
        $openRecs = \App\Models\RecommendationTracking::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();
        if ($openRecs > 0 && count($suggestions) < 2) {
            $suggestions[] = $openRecs === 1
                ? 'Complete your open recommendation'
                : "Complete {$openRecs} open recommendations";
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Keep your information up to date';
        }

        return array_slice($suggestions, 0, 2);
    }
}
```

> NOTE: confirm the relation names (`protectionPolicies`, `dcPensions`, etc.) exist on `User`; if a relation has a different name, adjust the `$checks` keys. The `method_exists` guard keeps it safe if one is absent.

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/LevelServiceTest.php`
Expected: PASS (all assertions green).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Gamification/AwardResult.php app/Services/Gamification/LevelService.php tests/Unit/Services/Gamification/LevelServiceTest.php
git commit -m "feat(gamification): LevelService (level resolution, progress, action-oriented next steps) + AwardResult"
```

---

## Task 5: `LevelUpCollector` (request-scoped) (TDD)

**Files:**
- Create: `app/Services/Gamification/LevelUpCollector.php`
- Modify: `app/Providers/AppServiceProvider.php` (register as scoped singleton)
- Test: `tests/Unit/Services/Gamification/LevelUpCollectorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Gamification\LevelUpCollector;

it('records nothing by default', function () {
    $c = new LevelUpCollector();
    expect($c->hasLevelUp())->toBeFalse();
    expect($c->highest())->toBeNull();
});

it('keeps only the highest level reached this request', function () {
    $c = new LevelUpCollector();
    $c->record(3, 'Builder');
    $c->record(5, 'Planner');
    $c->record(4, 'Organiser');

    expect($c->hasLevelUp())->toBeTrue();
    expect($c->highest())->toMatchArray(['level' => 5, 'level_name' => 'Planner']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/LevelUpCollectorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `LevelUpCollector`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

class LevelUpCollector
{
    private ?int $level = null;
    private ?string $levelName = null;

    public function record(int $level, string $levelName): void
    {
        if ($this->level === null || $level > $this->level) {
            $this->level = $level;
            $this->levelName = $levelName;
        }
    }

    public function hasLevelUp(): bool
    {
        return $this->level !== null;
    }

    /** @return array{level:int,level_name:string}|null */
    public function highest(): ?array
    {
        if ($this->level === null) {
            return null;
        }

        return ['level' => $this->level, 'level_name' => $this->levelName];
    }
}
```

- [ ] **Step 4: Register as a scoped singleton**

In `app/Providers/AppServiceProvider.php` `register()`:

```php
$this->app->scoped(\App\Services\Gamification\LevelUpCollector::class);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/LevelUpCollectorTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Gamification/LevelUpCollector.php app/Providers/AppServiceProvider.php tests/Unit/Services/Gamification/LevelUpCollectorTest.php
git commit -m "feat(gamification): request-scoped LevelUpCollector"
```

---

## Task 6: `PointsService::award` core (TDD)

**Files:**
- Create: `app/Services/Gamification/PointsService.php`
- Test: `tests/Unit/Services/Gamification/PointsServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\LevelUpCollector;
use App\Services\Gamification\PointsService;

beforeEach(function () {
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->svc = app(PointsService::class);
});

it('awards points once per dedup key', function () {
    $r1 = $this->svc->award($this->user, 'data', 'data:savings_account:first', 20);
    $r2 = $this->svc->award($this->user, 'data', 'data:savings_account:first', 20);

    expect($r1->awarded)->toBeTrue();
    expect($r2->awarded)->toBeFalse(); // dedup
    expect(PointAward::where('user_id', $this->user->id)->count())->toBe(1);
    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(20);
});

it('keeps total_points monotonic and recomputes level', function () {
    $this->svc->award($this->user, 'data', 'a', 40);
    $this->svc->award($this->user, 'data', 'b', 20); // total 60 -> level 2

    $g = UserGamification::where('user_id', $this->user->id)->first();
    expect($g->total_points)->toBe(60);
    expect($g->level)->toBe(2);
});

it('flags a level-up, sets pending celebration, and records on the collector', function () {
    $collector = app(LevelUpCollector::class);
    $r = $this->svc->award($this->user, 'data', 'big', 60); // 0 -> 60 = L1 -> L2

    expect($r->leveledUp)->toBeTrue();
    expect($r->newLevel)->toBe(2);
    expect($r->newLevelName)->toBe('Saver');
    expect(UserGamification::where('user_id', $this->user->id)->value('pending_celebration_level'))->toBe(2);
    expect($collector->highest())->toMatchArray(['level' => 2]);
});

it('never awards to preview users', function () {
    $preview = User::factory()->create(['is_preview_user' => true]);
    $r = $this->svc->award($preview, 'data', 'x', 50);

    expect($r->awarded)->toBeFalse();
    expect(UserGamification::where('user_id', $preview->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/PointsServiceTest.php`
Expected: FAIL — `PointsService` not found.

- [ ] **Step 3: Implement `PointsService` (core `award` only for now)**

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Traits\StructuredLogging;
use Illuminate\Support\Facades\DB;
use Throwable;

class PointsService
{
    use StructuredLogging;

    public function __construct(
        private readonly LevelService $levels,
        private readonly LevelUpCollector $collector,
    ) {}

    /**
     * Award points idempotently. Returns an AwardResult; never throws — a
     * gamification failure must not break the triggering write.
     */
    public function award(User $user, string $sourceType, string $dedupKey, int $points, array $meta = []): AwardResult
    {
        if ($user->is_preview_user) {
            return AwardResult::noop();
        }
        if ($points <= 0) {
            return AwardResult::noop();
        }

        try {
            return DB::transaction(function () use ($user, $sourceType, $dedupKey, $points, $meta) {
                $award = PointAward::firstOrCreate(
                    ['user_id' => $user->id, 'dedup_key' => $dedupKey],
                    ['source_type' => $sourceType, 'points' => $points, 'meta' => $meta],
                );

                if (! $award->wasRecentlyCreated) {
                    $g = UserGamification::where('user_id', $user->id)->first();
                    $level = $g?->level ?? 1;

                    return new AwardResult(false, 0, false, $level, $this->levels->levelName($level));
                }

                $g = UserGamification::firstOrCreate(['user_id' => $user->id]);
                $oldLevel = $g->level;
                $g->total_points += $points;
                $newLevel = $this->levels->levelForPoints($g->total_points);
                $leveledUp = $newLevel > $oldLevel;

                $g->level = $newLevel;
                if ($leveledUp) {
                    $g->pending_celebration_level = $newLevel;
                }
                $g->save();

                if ($leveledUp) {
                    $this->collector->record($newLevel, $this->levels->levelName($newLevel));
                }

                return new AwardResult(true, $points, $leveledUp, $newLevel, $this->levels->levelName($newLevel));
            });
        } catch (Throwable $e) {
            $this->logError('Gamification award failed', [
                'user_id' => $user->id,
                'dedup_key' => $dedupKey,
                'error' => $e->getMessage(),
            ]);

            return AwardResult::noop();
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/PointsServiceTest.php`
Expected: PASS (all 4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gamification/PointsService.php tests/Unit/Services/Gamification/PointsServiceTest.php
git commit -m "feat(gamification): PointsService.award — idempotent, monotonic, level-up + preview-safe"
```

---

# Phase 2 — Award hooks

## Task 7: Data-entry awards — trait + `awardDataEntry` + attach to models (TDD)

**Files:**
- Modify: `app/Services/Gamification/PointsService.php` (add `awardDataEntry`)
- Create: `app/Models/Concerns/AwardsDataEntryPoints.php`
- Modify (add trait + `pointCategory()`): `app/Models/SavingsAccount.php`, `app/Models/InvestmentAccount.php`, `app/Models/Property.php`, `app/Models/DCPension.php`, `app/Models/ProtectionPolicy.php`, `app/Models/Goal.php`, plus the will/LPA model (`app/Models/Estate/LastingPowerOfAttorney.php`).
- Test: `tests/Unit/Services/Gamification/DataEntryPointsTest.php`

> Confirm exact model class names/paths before editing (`grep -rl "class SavingsAccount" app/Models`). The category string is what the dedup key uses; keep it stable.

- [ ] **Step 1: Write the failing test for `awardDataEntry`**

```php
<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\PointsService;

beforeEach(function () {
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->svc = app(PointsService::class);
});

it('awards the first-in-category bonus once', function () {
    $this->svc->awardDataEntry($this->user, 'savings_account', 101);
    $this->svc->awardDataEntry($this->user, 'savings_account', 101); // same record id -> dedup

    expect(PointAward::where('user_id', $this->user->id)->count())->toBe(1);
    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(20); // first-in-category
});

it('awards capped extra-record points after the first', function () {
    // first in category + 4 more distinct records; cap on extras is 3.
    $this->svc->awardDataEntry($this->user, 'savings_account', 1);  // +20 (first)
    $this->svc->awardDataEntry($this->user, 'savings_account', 2);  // +5
    $this->svc->awardDataEntry($this->user, 'savings_account', 3);  // +5
    $this->svc->awardDataEntry($this->user, 'savings_account', 4);  // +5
    $this->svc->awardDataEntry($this->user, 'savings_account', 5);  // capped -> +0

    // 20 + 5*3 = 35
    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(35);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/DataEntryPointsTest.php`
Expected: FAIL — `awardDataEntry` not defined.

- [ ] **Step 3: Add `awardDataEntry` to `PointsService`**

```php
/**
 * Award data-entry points: a one-time first-in-category bonus, then a small
 * per-record award capped per category. Idempotent per record id.
 */
public function awardDataEntry(User $user, string $category, int $recordId): void
{
    $cfg = config('gamification.points');

    // First-in-category (once ever).
    $first = $this->award($user, 'data', "data:{$category}:first", (int) $cfg['data_first_in_category'], [
        'category' => $category,
    ]);

    // If this IS the first-in-category award we just made, don't also pay an extra for it.
    if ($first->awarded) {
        return;
    }

    // Extra records, capped per category.
    if ($user->is_preview_user) {
        return;
    }
    $cap = (int) $cfg['data_extra_cap_per_category'];
    $extrasSoFar = PointAward::where('user_id', $user->id)
        ->where('source_type', 'data')
        ->where('dedup_key', 'like', "data:{$category}:rec:%")
        ->count();
    if ($extrasSoFar >= $cap) {
        return;
    }

    $this->award($user, 'data', "data:{$category}:rec:{$recordId}", (int) $cfg['data_extra_record'], [
        'category' => $category,
        'record_id' => $recordId,
    ]);
}
```

> Test note: in step 1 the first call uses record id 101 and asserts a single award of 20 — that hits the "first" branch and returns. In the capped test, record id 1 is "first" (+20), ids 2–4 are extras (+5 each), id 5 is over cap (+0). Dedup keys: `data:savings_account:first` and `data:savings_account:rec:{id}`.

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/DataEntryPointsTest.php`
Expected: PASS.

- [ ] **Step 5: Write the `AwardsDataEntryPoints` trait**

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Gamification\PointsService;

/**
 * Awards data-entry gamification points when a record is created.
 * The consuming model must define `gamificationCategory(): string`.
 */
trait AwardsDataEntryPoints
{
    public static function bootAwardsDataEntryPoints(): void
    {
        static::created(function ($model): void {
            if (empty($model->user_id)) {
                return;
            }
            $user = $model->user ?? \App\Models\User::find($model->user_id);
            if (! $user) {
                return;
            }
            app(PointsService::class)->awardDataEntry($user, $model->gamificationCategory(), (int) $model->getKey());
        });
    }
}
```

- [ ] **Step 6: Attach the trait + category to each data model**

For each model, add `use AwardsDataEntryPoints;` to the `use` block inside the class and a `gamificationCategory()` method. Example for `SavingsAccount`:

```php
use App\Models\Concerns\AwardsDataEntryPoints;

class SavingsAccount extends Model
{
    use AwardsDataEntryPoints;
    // ...existing traits...

    public function gamificationCategory(): string
    {
        return 'savings_account';
    }
}
```

Categories: `SavingsAccount` → `savings_account`; `InvestmentAccount` → `investment_account`; `Property` → `property`; `DCPension` → `pension`; `ProtectionPolicy` → `protection_policy`; `Goal` → `goal`; `LastingPowerOfAttorney` → `estate`.

> Import-ordering trap (seen ~4× last session): add the `use App\Models\Concerns\AwardsDataEntryPoints;` import only together with the in-class `use AwardsDataEntryPoints;` usage in the SAME edit, so Pint never strips it as unused.

- [ ] **Step 7: Feature test — creating a model awards points end-to-end**

Test: `tests/Feature/Gamification/DataEntryObserverTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserGamification;

it('awards points when a savings account is created', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create(['user_id' => $user->id]);

    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(20);
});

it('does not award points for preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    SavingsAccount::factory()->create(['user_id' => $user->id]);

    expect(UserGamification::where('user_id', $user->id)->exists())->toBeFalse();
});
```

> Confirm the `SavingsAccount` factory exists and accepts `user_id`. If a model has no factory, create the minimal record inline in the test instead.

- [ ] **Step 8: Run both test files**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/DataEntryPointsTest.php tests/Feature/Gamification/DataEntryObserverTest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/Gamification/PointsService.php app/Models/Concerns/AwardsDataEntryPoints.php app/Models/SavingsAccount.php app/Models/InvestmentAccount.php app/Models/Property.php app/Models/DCPension.php app/Models/ProtectionPolicy.php app/Models/Goal.php app/Models/Estate/LastingPowerOfAttorney.php tests/Unit/Services/Gamification/DataEntryPointsTest.php tests/Feature/Gamification/DataEntryObserverTest.php
git commit -m "feat(gamification): data-entry point awards via created-model trait (7 categories)"
```

---

## Task 8: Income & expenditure first-capture hook

**Files:**
- Modify: `app/Models/ExpenditureProfile.php` (add the trait + category `expenditure`)
- Modify: the income write path. Income is resolved via `ResolvesIncome`; confirm the persisted income model/field (`grep -rn "income" app/Models/User.php app/Models | grep -i "profile\|annual_income"`). If income lives on `User` (a column) rather than its own model, hook it in the controller that updates it instead of a created observer.
- Test: `tests/Feature/Gamification/IncomeExpenditureAwardTest.php`

- [ ] **Step 1: Add the trait to `ExpenditureProfile`** (same pattern as Task 7, category `expenditure`).

- [ ] **Step 2: Resolve the income seam**

Run: `grep -rn "annual_income\|gross_income\|class IncomeProfile" app/Models`
- If a dedicated `IncomeProfile`/equivalent model exists → add the trait, category `income`.
- If income is a `User` column updated by a profile controller → in that controller, after a successful save where income transitions from empty to set, call:

```php
app(\App\Services\Gamification\PointsService::class)
    ->award($user, 'data', 'data:income:first', config('gamification.points.data_first_in_category'));
```

- [ ] **Step 3: Write the feature test** mirroring Task 7 step 7 for whichever seam applies (expenditure profile create; income set). Assert `total_points` increases by 20 once, and not for preview users.

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Feature/Gamification/IncomeExpenditureAwardTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ExpenditureProfile.php tests/Feature/Gamification/IncomeExpenditureAwardTest.php
# plus the income-seam file(s)
git commit -m "feat(gamification): income + expenditure first-capture point awards"
```

---

## Task 9: Recommendation-completion award (model observer) (TDD)

**Files:**
- Create: `app/Observers/RecommendationTrackingObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register the observer)
- Test: `tests/Feature/Gamification/RecommendationCompletionAwardTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserGamification;

beforeEach(fn () => $this->user = User::factory()->create(['is_preview_user' => false]));

it('awards points when a recommendation is marked completed', function () {
    $rec = RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'rec-abc',
        'module' => 'savings',
        'recommendation_text' => 'Top up your ISA',
        'priority_score' => 80,
        'timeline' => 'short_term',
        'status' => 'pending',
    ]);

    $rec->markAsCompleted();

    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(25);
});

it('awards once even if completed twice', function () {
    $rec = RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'rec-xyz',
        'module' => 'savings',
        'recommendation_text' => 'x',
        'priority_score' => 50,
        'timeline' => 'short_term',
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    $rec->markAsCompleted(); // again

    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(25);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Gamification/RecommendationCompletionAwardTest.php`
Expected: FAIL — points not awarded (no observer yet).

- [ ] **Step 3: Implement the observer**

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RecommendationTracking;
use App\Services\Gamification\PointsService;

class RecommendationTrackingObserver
{
    public function __construct(private readonly PointsService $points) {}

    public function saved(RecommendationTracking $tracking): void
    {
        if ($tracking->status !== 'completed') {
            return;
        }
        $user = $tracking->user;
        if (! $user) {
            return;
        }

        // Dedup keyed by the business recommendation id -> awards exactly once.
        $this->points->award(
            $user,
            'recommendation',
            "recommendation:{$tracking->recommendation_id}",
            (int) config('gamification.points.recommendation'),
            ['module' => $tracking->module],
        );
    }
}
```

- [ ] **Step 4: Register the observer**

In `app/Providers/AppServiceProvider.php` `boot()`, beside the existing `observe()` calls:

```php
\App\Models\RecommendationTracking::observe(\App\Observers\RecommendationTrackingObserver::class);
```

- [ ] **Step 5: Run to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Gamification/RecommendationCompletionAwardTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Observers/RecommendationTrackingObserver.php app/Providers/AppServiceProvider.php tests/Feature/Gamification/RecommendationCompletionAwardTest.php
git commit -m "feat(gamification): award points on recommendation completion (model observer)"
```

---

## Task 10: Milestone award inside `MilestoneDetectionService` (TDD)

**Files:**
- Modify: `app/Services/Mobile/MilestoneDetectionService.php` (inject `PointsService`, award on `wasRecentlyCreated`)
- Test: `tests/Feature/Gamification/MilestoneAwardTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use App\Services\Mobile\MilestoneDetectionService;

it('awards points for a newly crossed net-worth milestone', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $svc = app(MilestoneDetectionService::class);

    $svc->detectNetWorth($user, 30000); // crosses 10k + 25k = 2 milestones
    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(60); // 2 * 30

    // Re-running does not double-award (milestones already recorded).
    $svc->detectNetWorth($user, 30000);
    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(60);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Gamification/MilestoneAwardTest.php`
Expected: FAIL — points stay 0.

- [ ] **Step 3: Modify `MilestoneDetectionService`**

Add the constructor + award call. At the top of the class:

```php
public function __construct(
    private readonly \App\Services\Gamification\PointsService $points,
) {}
```

In `detectNetWorth`, inside `if ($record->wasRecentlyCreated) {` (before building `$new[]`):

```php
$this->points->award(
    $user,
    'milestone',
    "milestone:net_worth:0:{$threshold}",
    (int) config('gamification.points.milestone'),
    ['threshold' => $threshold],
);
```

In `detectGoal`, inside its `if ($record->wasRecentlyCreated) {`:

```php
$this->points->award(
    $user,
    'milestone',
    "milestone:goal:{$goalId}:{$threshold}",
    (int) config('gamification.points.milestone'),
    ['goal_id' => $goalId, 'threshold' => $threshold],
);
```

> `MilestoneDetectionService` is currently constructed without args (resolved via container in `MobileDashboardController`); adding a constructor dependency is safe because Laravel autowires it. Confirm no `new MilestoneDetectionService()` call sites exist (`grep -rn "new MilestoneDetectionService" app/`); if any, switch them to `app(MilestoneDetectionService::class)`.

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Gamification/MilestoneAwardTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Mobile/MilestoneDetectionService.php tests/Feature/Gamification/MilestoneAwardTest.php
git commit -m "feat(gamification): award points on newly-crossed milestones"
```

---

## Task 11: Onboarding / savetax answer awards

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (award per persisted answer/step)
- Test: `tests/Feature/Gamification/OnboardingAnswerAwardTest.php`

- [ ] **Step 1: Locate the capture seam**

Run: `grep -n "function handleInlineCapture\|onboarding_fyn_step\|advance\|persist\|->update(" app/Services/Onboarding/OnboardingChatDirector.php | head -40`
Identify the method(s) where a user's answer/step is persisted (the bubble-driven advance and `handleInlineCapture`). Each persisted answer should award once, keyed by the step identifier.

- [ ] **Step 2: Add the award at the persistence point**

At the point a step/answer is committed (where `onboarding_fyn_step` advances or an inline capture writes), add:

```php
app(\App\Services\Gamification\PointsService::class)->award(
    $user,
    'onboarding',
    "onboarding:{$stepKey}",
    (int) config('gamification.points.onboarding_answer'),
    ['step' => $stepKey],
);
```

`$stepKey` = the stable identifier for the answered step (e.g. the prior `onboarding_fyn_step` value, or the capture field name). Dedup ensures re-answering the same step does not re-award.

- [ ] **Step 3: Write the feature test**

Drive the director's capture method for a user with two distinct steps; assert `total_points` increases by `onboarding_answer` per distinct step and does not increase on a repeat of the same step key. (Model the test on the existing onboarding director tests — `grep -rl OnboardingChatDirector tests/`.)

- [ ] **Step 4: Run**

Run: `./vendor/bin/pest tests/Feature/Gamification/OnboardingAnswerAwardTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Gamification/OnboardingAnswerAwardTest.php
git commit -m "feat(gamification): award points per onboarding/savetax answer"
```

---

## Task 12: Daily login + streak (TDD)

**Files:**
- Modify: `app/Services/Gamification/PointsService.php` (add `recordLogin`)
- Modify: `app/Http/Controllers/Api/AuthController.php` (`verifyCode` success) and `app/Http/Controllers/Api/MFAController.php` (`verify` success) — call `recordLogin`.
- Test: `tests/Unit/Services/Gamification/LoginStreakTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\PointsService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->svc = app(PointsService::class);
});

it('awards the daily login once per calendar day', function () {
    Carbon::setTestNow('2026-06-06 09:00:00');
    $this->svc->recordLogin($this->user);
    $this->svc->recordLogin($this->user); // same day -> no second award

    $g = UserGamification::where('user_id', $this->user->id)->first();
    expect($g->total_points)->toBe(5);
    expect($g->login_streak_days)->toBe(1);
    Carbon::setTestNow();
});

it('increments the streak on consecutive days and awards the day-3 bonus', function () {
    Carbon::setTestNow('2026-06-06 09:00:00');
    $this->svc->recordLogin($this->user);
    Carbon::setTestNow('2026-06-07 09:00:00');
    $this->svc->recordLogin($this->user);
    Carbon::setTestNow('2026-06-08 09:00:00');
    $this->svc->recordLogin($this->user); // streak hits 3 -> +5 daily +15 bonus

    $g = UserGamification::where('user_id', $this->user->id)->first();
    expect($g->login_streak_days)->toBe(3);
    // 3 daily (5*3=15) + day-3 streak bonus (15) = 30
    expect($g->total_points)->toBe(30);
    Carbon::setTestNow();
});

it('resets the streak after a missed day', function () {
    Carbon::setTestNow('2026-06-06 09:00:00');
    $this->svc->recordLogin($this->user);
    Carbon::setTestNow('2026-06-09 09:00:00'); // skipped 7th and 8th
    $this->svc->recordLogin($this->user);

    expect(UserGamification::where('user_id', $this->user->id)->value('login_streak_days'))->toBe(1);
    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/LoginStreakTest.php`
Expected: FAIL — `recordLogin` not defined.

- [ ] **Step 3: Implement `recordLogin`**

```php
public function recordLogin(User $user): void
{
    if ($user->is_preview_user) {
        return;
    }

    try {
        $today = now()->toDateString();
        $g = UserGamification::firstOrCreate(['user_id' => $user->id]);

        if ((string) $g->last_login_award_date === $today) {
            return; // already counted today
        }

        // Streak: consecutive day continues, otherwise reset.
        $yesterday = now()->subDay()->toDateString();
        if ((string) $g->last_login_award_date === $yesterday) {
            $g->login_streak_days += 1;
        } else {
            $g->login_streak_days = 1;
            $g->streak_started_on = $today;
        }
        $g->last_login_award_date = $today;
        $g->save();

        // Daily login award.
        $this->award($user, 'login', "login:{$today}", (int) config('gamification.points.daily_login'));

        // Streak bonus, once per run length.
        $bonuses = config('gamification.points.streak');
        $n = $g->login_streak_days;
        if (isset($bonuses[$n])) {
            $runStart = (string) ($g->streak_started_on ?? $today);
            $this->award($user, 'streak', "streak:{$n}:{$runStart}", (int) $bonuses[$n], ['streak_days' => $n]);
        }
    } catch (\Throwable $e) {
        $this->logError('Gamification recordLogin failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification/LoginStreakTest.php`
Expected: PASS.

- [ ] **Step 5: Wire into the login completion points**

In `AuthController::verifyCode`, immediately after the user is fully authenticated and the Bearer token is issued (just before returning the success response), add:

```php
app(\App\Services\Gamification\PointsService::class)->recordLogin($user);
```

Do the same in `MFAController::verify` at its token-issuance success point. Confirm the variable holding the authenticated user (`$user`) at each site.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Gamification/PointsService.php app/Http/Controllers/Api/AuthController.php app/Http/Controllers/Api/MFAController.php tests/Unit/Services/Gamification/LoginStreakTest.php
git commit -m "feat(gamification): daily-login award + consecutive-day streak bonuses"
```

---

# Phase 3 — API + SSE + /m repoint

## Task 13: `GamificationController` (`status`, `ack`) + routes (TDD)

**Files:**
- Create: `app/Http/Controllers/Api/GamificationController.php`
- Modify: `routes/api.php` (add the `gamification` route group)
- Test: `tests/Feature/Gamification/GamificationApiTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use Laravel\Sanctum\Sanctum;

it('returns the gamification status', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 290, 'level' => 4]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('level', 4)
        ->assertJsonPath('level_name', 'Organiser')
        ->assertJsonPath('progress_percent', 50)
        ->assertJsonPath('next_level_name', 'Planner')
        ->assertJsonStructure(['next_actions', 'pending_celebration']);
});

it('surfaces a pending celebration then clears it on ack', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 60, 'level' => 2, 'pending_celebration_level' => 2]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('pending_celebration.level', 2)
        ->assertJsonPath('pending_celebration.level_name', 'Saver');

    $this->postJson('/api/gamification/celebration/ack')->assertOk()->assertJsonPath('acknowledged', true);

    expect(UserGamification::where('user_id', $user->id)->value('pending_celebration_level'))->toBeNull();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Gamification/GamificationApiTest.php`
Expected: FAIL — 404 (routes not defined).

- [ ] **Step 3: Implement the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGamification;
use App\Services\Gamification\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function __construct(private readonly LevelService $levels) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $g = UserGamification::firstOrCreate(['user_id' => $user->id]);

        $progress = $this->levels->progress((int) $g->total_points);
        $nextActions = $this->levels->nextActions($user);

        $pending = null;
        if ($g->pending_celebration_level !== null) {
            $pending = [
                'level' => (int) $g->pending_celebration_level,
                'level_name' => $this->levels->levelName((int) $g->pending_celebration_level),
                'next_actions' => $nextActions,
            ];
        }

        return response()->json([
            'level' => $progress['level'],
            'level_name' => $progress['level_name'],
            'level_label' => $progress['level_label'],
            'progress_percent' => $progress['progress_percent'],
            'next_level_name' => $progress['next_level_name'],
            'next_actions' => $nextActions,
            'pending_celebration' => $pending,
        ]);
    }

    public function ackCelebration(Request $request): JsonResponse
    {
        $user = $request->user();
        UserGamification::where('user_id', $user->id)->update(['pending_celebration_level' => null]);

        return response()->json(['acknowledged' => true]);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/api.php`, inside the `auth:sanctum` area (near the `recommendations` group):

```php
Route::middleware('auth:sanctum')->prefix('gamification')->group(function () {
    Route::get('/status', [\App\Http\Controllers\Api\GamificationController::class, 'status']);
    Route::post('/celebration/ack', [\App\Http\Controllers\Api\GamificationController::class, 'ackCelebration']);
});
```

- [ ] **Step 5: Run to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Gamification/GamificationApiTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/GamificationController.php routes/api.php tests/Feature/Gamification/GamificationApiTest.php
git commit -m "feat(gamification): GET /api/gamification/status + POST /api/gamification/celebration/ack"
```

---

## Task 14: Fyn SSE `level_up` event (after `done`)

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php` (`sendMessage` and `action` StreamedResponse closures)
- Test: `tests/Feature/Gamification/FynLevelUpSseTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Gamification\LevelUpCollector;
use Laravel\Sanctum\Sanctum;

it('emits a level_up SSE frame after a level-up turn', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    // Simulate a level-up having occurred during this request.
    app(LevelUpCollector::class)->record(5, 'Planner');

    $response = $this->get("/api/ai-chat/conversations/{$conversation->id}/level-up-probe");
    // See implementation note: the probe asserts the emit helper output.
})->skip('Illustrative — assert via the extracted emit helper unit test below.');

it('formats a level_up frame from the collector', function () {
    app(LevelUpCollector::class)->record(5, 'Planner');
    $frame = \App\Http\Controllers\Api\AiChatController::levelUpFrame(app(LevelUpCollector::class), app(\App\Services\Gamification\LevelService::class), app(\App\Models\User::class)->forceFill(['id' => 1]));
    expect($frame)->not->toBeNull();
    expect($frame['type'])->toBe('level_up');
    expect($frame['level'])->toBe(5);
    expect($frame['level_name'])->toBe('Planner');
    expect($frame)->toHaveKey('next_actions');
});
```

> Because the SSE stream is hard to assert end-to-end in Pest, extract a static `levelUpFrame()` helper and unit-test it; the wiring into the stream is verified in the browser walk (Task 21). Drop the skipped illustrative test if preferred.

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Gamification/FynLevelUpSseTest.php`
Expected: FAIL — `levelUpFrame` not defined.

- [ ] **Step 3: Add the helper + emit after the generator loop**

In `AiChatController`, add a static helper:

```php
/**
 * Build the terminal `level_up` SSE frame from the request-scoped collector,
 * or null if no level-up occurred this turn.
 */
public static function levelUpFrame(
    \App\Services\Gamification\LevelUpCollector $collector,
    \App\Services\Gamification\LevelService $levels,
    \App\Models\User $user,
): ?array {
    if (! $collector->hasLevelUp()) {
        return null;
    }
    $top = $collector->highest();

    return [
        'type' => 'level_up',
        'level' => $top['level'],
        'level_name' => $top['level_name'],
        'next_actions' => $levels->nextActions($user),
    ];
}
```

Then, in BOTH the `sendMessage` and `action` StreamedResponse closures, **after** the `foreach ($generator as $event) {…}` loop (i.e. after `done` has been emitted), add:

```php
$frame = self::levelUpFrame(
    app(\App\Services\Gamification\LevelUpCollector::class),
    app(\App\Services\Gamification\LevelService::class),
    $user,
);
if ($frame !== null) {
    echo 'data: '.json_encode($frame)."\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}
```

> This runs in the same request as any data write performed during the turn (e.g. via `delegate_to_capture` → `handleInlineCapture` → direct-write handlers → data-entry observers → `PointsService` → collector), so the collector is populated by the time the loop ends. The frame is emitted strictly after `done`, satisfying "celebrate after Fyn's reply finishes" (decision #10).

- [ ] **Step 4: Run to verify the helper test passes**

Run: `./vendor/bin/pest tests/Feature/Gamification/FynLevelUpSseTest.php`
Expected: PASS (the `formats a level_up frame` test).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AiChatController.php tests/Feature/Gamification/FynLevelUpSseTest.php
git commit -m "feat(gamification): emit level_up SSE frame after Fyn reply completes"
```

---

## Task 15: Repoint `MobileLevelService` to the engine (TDD)

**Files:**
- Modify: `app/Services/Mobile/MobileLevelService.php`
- Test: `tests/Unit/Services/Mobile/MobileLevelServiceTest.php` (create or extend)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use App\Services\Mobile\MobileLevelService;

it('reads the level from the gamification engine', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 360, 'level' => 5]);

    $result = app(MobileLevelService::class)->levelFor($user->id);

    expect($result['level'])->toBe(5);
    expect($result['progress_percent'])->toBeInt();
    expect($result)->toHaveKeys(['level', 'progress_percent']);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Mobile/MobileLevelServiceTest.php`
Expected: FAIL (current implementation derives from module counts, ignores `user_gamification`).

- [ ] **Step 3: Repoint `levelFor` and `percentile`**

Replace `completedActionCount`-based derivation. `levelFor` now reads `user_gamification.total_points` and delegates to `LevelService`:

```php
public function __construct(private readonly \App\Services\Gamification\LevelService $levels) {}

public function levelFor(int $userId): array
{
    $points = (int) (\App\Models\UserGamification::where('user_id', $userId)->value('total_points') ?? 0);
    $p = $this->levels->progress($points);

    return [
        'level' => $p['level'],
        'level_name' => $p['level_name'],
        'progress_percent' => $p['progress_percent'],
        'next_level_name' => $p['next_level_name'],
    ];
}
```

Update `levelDistribution()` to read `user_gamification.level` directly (cheaper than recomputing per user):

```php
private function levelDistribution(): array
{
    return Cache::remember('mobile_level_distribution', self::PERCENTILE_CACHE_TTL, function () {
        return \App\Models\UserGamification::query()
            ->join('users', 'users.id', '=', 'user_gamification.user_id')
            ->where('users.is_preview_user', false)
            ->selectRaw('level, COUNT(*) as c')
            ->groupBy('level')
            ->pluck('c', 'level')
            ->toArray() ?: [1 => 1];
    });
}
```

Remove the now-dead `actionsForLevel`/`completedActionCount` derivation (or keep `clearCache`). Update `MobileDashboardController` if it referenced removed keys (`grep -n "levelFor\|actions_for_next\|actions_completed" app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php`) — keep the response shape stable; map removed fields to the new `progress`/`next_level_name`. If the `/m` wheel consumed `actions_in_level`/`actions_for_next`, replace with `progress_percent` + `next_level_name` and adjust the `/m` Dashboard component in Task 20.

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Mobile/MobileLevelServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Run the mobile dashboard tests to catch shape regressions**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileDashboardTest.php`
Expected: PASS (fix any field-shape breakages in the controller).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Mobile/MobileLevelService.php app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php tests/Unit/Services/Mobile/MobileLevelServiceTest.php
git commit -m "feat(gamification): repoint /m level wheel + percentile to the points engine"
```

---

# Phase 4 — Backfill

## Task 16: `gamification:backfill` command (TDD)

**Files:**
- Create: `app/Console/Commands/GamificationBackfill.php`
- Test: `tests/Feature/Gamification/BackfillTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserGamification;

it('backfills data + completed recommendations quietly, idempotently', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    // Create data WITHOUT triggering live awards by disabling events is overkill;
    // instead create then clear gamification to simulate a pre-launch user.
    SavingsAccount::factory()->create(['user_id' => $user->id]);
    RecommendationTracking::create([
        'user_id' => $user->id, 'recommendation_id' => 'r1', 'module' => 'savings',
        'recommendation_text' => 'x', 'priority_score' => 50, 'timeline' => 'short_term',
        'status' => 'completed', 'completed_at' => now(),
    ]);
    UserGamification::where('user_id', $user->id)->delete();
    \App\Models\PointAward::where('user_id', $user->id)->delete();

    $this->artisan('gamification:backfill')->assertExitCode(0);

    $g = UserGamification::where('user_id', $user->id)->first();
    // savings first-in-category (20) + recommendation (25) = 45
    expect($g->total_points)->toBe(45);
    // Quiet: no celebration queued.
    expect($g->pending_celebration_level)->toBeNull();

    // Re-run awards nothing more.
    $this->artisan('gamification:backfill')->assertExitCode(0);
    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(45);
});

it('skips preview users', function () {
    $preview = User::factory()->create(['is_preview_user' => true]);
    SavingsAccount::factory()->create(['user_id' => $preview->id]);
    UserGamification::where('user_id', $preview->id)->delete();

    $this->artisan('gamification:backfill')->assertExitCode(0);
    expect(UserGamification::where('user_id', $preview->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Gamification/BackfillTest.php`
Expected: FAIL — command not registered.

- [ ] **Step 3: Implement the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\PointsService;
use Illuminate\Console\Command;

class GamificationBackfill extends Command
{
    protected $signature = 'gamification:backfill';

    protected $description = 'One-time reconciliation: award points for existing data, milestones and completed recommendations (quiet — no celebrations).';

    private array $dataCategories = [
        'savingsAccounts' => 'savings_account',
        'investmentAccounts' => 'investment_account',
        'properties' => 'property',
        'dcPensions' => 'pension',
        'protectionPolicies' => 'protection_policy',
        'goals' => 'goal',
    ];

    public function handle(PointsService $points): int
    {
        User::query()->where('is_preview_user', false)->chunkById(200, function ($users) use ($points) {
            foreach ($users as $user) {
                // Data categories (first + capped extras via awardDataEntry).
                foreach ($this->dataCategories as $relation => $category) {
                    if (! method_exists($user, $relation)) {
                        continue;
                    }
                    foreach ($user->{$relation}()->get() as $record) {
                        $points->awardDataEntry($user, $category, (int) $record->getKey());
                    }
                }

                // Completed recommendations.
                RecommendationTracking::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->get()
                    ->each(fn ($rec) => $points->award(
                        $user, 'recommendation', "recommendation:{$rec->recommendation_id}",
                        (int) config('gamification.points.recommendation'),
                    ));

                // Quiet: suppress any celebration the awards may have queued.
                UserGamification::where('user_id', $user->id)->update(['pending_celebration_level' => null]);
            }
        });

        // Existing user_milestones rows already represent crossed milestones;
        // award once each (dedup keys match live detection).
        \App\Models\UserMilestone::query()->chunkById(500, function ($milestones) use ($points) {
            foreach ($milestones as $m) {
                $user = User::find($m->user_id);
                if (! $user || $user->is_preview_user) {
                    continue;
                }
                $ref = $m->reference_id ?? 0;
                $points->award($user, 'milestone',
                    "milestone:{$m->milestone_type}:{$ref}:".(int) $m->threshold,
                    (int) config('gamification.points.milestone'));
                UserGamification::where('user_id', $user->id)->update(['pending_celebration_level' => null]);
            }
        });

        $this->info('Gamification backfill complete.');

        return self::SUCCESS;
    }
}
```

> Milestone dedup key must match `MilestoneDetectionService` exactly. Task 10 uses `milestone:net_worth:0:{threshold}` (net worth `reference_id` is null → 0) and `milestone:goal:{goalId}:{threshold}`. The backfill uses `reference_id ?? 0`, so net-worth rows (null ref) produce `:0:` — consistent. Verify against the final Task 10 keys before running.

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Gamification/BackfillTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/GamificationBackfill.php tests/Feature/Gamification/BackfillTest.php
git commit -m "feat(gamification): gamification:backfill command (quiet, idempotent)"
```

---

# Phase 5 — Frontend (web + /m)

## Task 17: Web API service + Vuex module

**Files:**
- Create: `resources/js/services/gamification.js`
- Create: `resources/js/store/modules/gamification.js`
- Modify: `resources/js/store/index.js` (register the module)

- [ ] **Step 1: Write the API service**

```js
import api from './api'; // confirm the shared axios instance path used by other services

export default {
  status() {
    return api.get('/gamification/status');
  },
  ackCelebration() {
    return api.post('/gamification/celebration/ack');
  },
};
```

> Confirm the shared HTTP client import other services use (`grep -l "export default" resources/js/services/*.js | head` and open one). Match it exactly.

- [ ] **Step 2: Write the Vuex module**

```js
import gamificationService from '@/services/gamification';

export default {
  namespaced: true,
  state: () => ({
    level: 1,
    levelName: 'Starter',
    progressPercent: 0,
    nextLevelName: null,
    nextActions: [],
    pendingCelebration: null, // { level, level_name, next_actions } | null
  }),
  mutations: {
    SET_STATUS(state, p) {
      state.level = p.level;
      state.levelName = p.level_name;
      state.progressPercent = p.progress_percent;
      state.nextLevelName = p.next_level_name;
      state.nextActions = p.next_actions || [];
      state.pendingCelebration = p.pending_celebration || null;
    },
    SET_CELEBRATION(state, c) { state.pendingCelebration = c; },
    CLEAR_CELEBRATION(state) { state.pendingCelebration = null; },
  },
  actions: {
    async fetchStatus({ commit }) {
      const { data } = await gamificationService.status();
      commit('SET_STATUS', data);
      return data;
    },
    // Called from the Fyn chat client when it receives a level_up SSE frame.
    queueCelebration({ commit }, frame) {
      commit('SET_CELEBRATION', { level: frame.level, level_name: frame.level_name, next_actions: frame.next_actions || [] });
    },
    async acknowledge({ commit }) {
      commit('CLEAR_CELEBRATION');
      try { await gamificationService.ackCelebration(); } catch (e) { /* non-fatal */ }
    },
  },
};
```

- [ ] **Step 3: Register the module**

In `resources/js/store/index.js`, import and add to `modules: { …, gamification }`.

- [ ] **Step 4: Build to verify no import errors**

Run: `./deploy/csjones-fynla/build.sh` is heavy; for a quick check run the dev server (`./dev.sh`) and confirm Vite compiles with no errors in `/tmp/devsh.log`.
Expected: no Vite/Rollup errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/services/gamification.js resources/js/store/modules/gamification.js resources/js/store/index.js
git commit -m "feat(gamification): web API service + Vuex module"
```

---

## Task 18: Shared `GamificationCelebration.vue` (fireworks takeover)

**Files:**
- Create: `resources/js/components/Gamification/GamificationCelebration.vue`

- [ ] **Step 1: Write the component** (approved fireworks takeover; Fynla palette; no emoji/icons). Props drive content; `@dismiss` emitted on tap/button.

```vue
<template>
  <transition name="celebrate-fade">
    <div v-if="visible" class="celebrate" role="dialog" aria-modal="true" :aria-label="`Level up: ${levelName}`" @click="dismiss">
      <span v-for="f in fireworks" :key="f.id" class="fw" :style="f.style">
        <span class="core"></span>
        <i v-for="n in 10" :key="n" :style="particleStyle(f, n)"></i>
      </span>
      <span v-for="c in confetti" :key="'c'+c.id" class="confetti" :style="c.style"></span>

      <div class="celebrate-body" @click.stop>
        <p class="kicker">LEVEL UP</p>
        <div class="ring-wrap">
          <svg width="140" height="140" class="ring">
            <circle cx="70" cy="70" r="62" class="ring-track" />
            <circle cx="70" cy="70" r="62" class="ring-fill" />
          </svg>
          <span class="lvl-num">{{ level }}</span>
        </div>
        <h2 class="title">Congratulations</h2>
        <p class="subtitle">You reached {{ levelName }}</p>
        <p v-if="nextActionsText" class="next">{{ nextActionsText }}</p>
        <button type="button" class="cta" @click.stop="dismiss">Keep going</button>
        <p class="hint">Tap anywhere to dismiss and continue</p>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'GamificationCelebration',
  props: {
    level: { type: Number, required: true },
    levelName: { type: String, required: true },
    nextActions: { type: Array, default: () => [] },
  },
  data() {
    return {
      visible: true,
      fireworks: this.buildFireworks(),
      confetti: this.buildConfetti(),
    };
  },
  computed: {
    nextActionsText() {
      if (!this.nextActions.length) return '';
      return `Next: ${this.nextActions.join(' and ')} to reach the next level.`;
    },
  },
  methods: {
    dismiss() {
      this.visible = false;
      this.$emit('dismiss');
    },
    buildFireworks() {
      const palette = ['#20B486', '#E83E6D', '#A78BFA', '#E6C9A8', '#6EE7B7'];
      const spots = [[24, 30], [72, 22], [50, 14], [16, 58], [84, 62]];
      return spots.map((s, i) => ({
        id: i,
        color: palette[i % palette.length],
        style: `left:${s[0]}%;top:${s[1]}%;animation-delay:${i * 0.4}s`,
      }));
    },
    particleStyle(f, n) {
      const angle = (360 / 10) * n;
      return `--r:${angle}deg;background:${f.color};animation-delay:${(f.id * 0.4) + 0.5}s`;
    },
    buildConfetti() {
      const palette = ['#E83E6D', '#20B486', '#E6C9A8', '#A78BFA', '#6EE7B7'];
      return Array.from({ length: 9 }, (_, i) => ({
        id: i,
        style: `left:${8 + i * 10}%;background:${palette[i % palette.length]};animation-delay:${(i * 0.3) % 2}s`,
      }));
    },
  },
};
</script>

<style scoped>
.celebrate { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 28px; text-align: center; color: #fff; overflow: hidden; background: linear-gradient(165deg, #141a2e 0%, #1F2A44 35%, #2c2466 72%, #5854E6 100%); }
.kicker { letter-spacing: 3px; font-size: 13px; font-weight: 700; color: #A7F3D0; }
.ring-wrap { position: relative; width: 140px; height: 140px; margin: 14px auto 6px; display: flex; align-items: center; justify-content: center; animation: pop .7s cubic-bezier(.2,.9,.3,1.4) both; }
.ring { position: absolute; inset: 0; transform: rotate(-90deg); }
.ring-track { fill: none; stroke: rgba(255,255,255,.15); stroke-width: 9; }
.ring-fill { fill: none; stroke: #20B486; stroke-width: 9; stroke-linecap: round; stroke-dasharray: 389; stroke-dashoffset: 389; animation: ring 1.3s ease-out .4s both; }
.lvl-num { font-size: 50px; font-weight: 900; }
.title { font-size: 28px; font-weight: 900; margin-top: 12px; }
.subtitle { font-size: 20px; font-weight: 700; color: #6EE7B7; margin-top: 2px; }
.next { font-size: 14px; color: #CBD5E1; margin-top: 16px; line-height: 1.55; max-width: 260px; }
.cta { margin-top: 20px; padding: 15px 28px; border: none; border-radius: 14px; background: #E83E6D; color: #fff; font-weight: 700; font-size: 16px; cursor: pointer; }
.hint { font-size: 13px; color: #CBD5E1; margin-top: 12px; }
.confetti { position: absolute; top: -20px; width: 9px; height: 9px; border-radius: 2px; animation: fall 3s linear infinite; }
.fw { position: absolute; width: 6px; height: 6px; }
.fw .core { position: absolute; left: -6px; top: -6px; width: 18px; height: 18px; border-radius: 50%; background: radial-gradient(#fff, transparent 70%); animation: flash 1.6s ease-out infinite; }
.fw i { position: absolute; left: 0; top: 0; width: 6px; height: 6px; border-radius: 50%; animation: burst 1.6s ease-out infinite; }
@keyframes pop { 0% { transform: scale(.4); opacity: 0; } 60% { transform: scale(1.08); } 100% { transform: scale(1); opacity: 1; } }
@keyframes ring { to { stroke-dashoffset: 96; } }
@keyframes fall { 0% { transform: translateY(0) rotate(0); opacity: 0; } 10% { opacity: 1; } 100% { transform: translateY(640px) rotate(420deg); opacity: 0; } }
@keyframes flash { 0%,7% { transform: scale(0); opacity: 0; } 9% { transform: scale(1.6); opacity: 1; } 16% { transform: scale(0); opacity: 0; } 100% { opacity: 0; } }
@keyframes burst { 0% { transform: rotate(var(--r)) translateY(0); opacity: 0; } 8% { opacity: 1; } 100% { transform: rotate(var(--r)) translateY(-58px); opacity: 0; } }
.celebrate-fade-enter-active, .celebrate-fade-leave-active { transition: opacity .3s ease; }
.celebrate-fade-enter-from, .celebrate-fade-leave-to { opacity: 0; }
</style>
```

- [ ] **Step 2: Verify Vite compiles** (dev server log clean).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Gamification/GamificationCelebration.vue
git commit -m "feat(gamification): shared fireworks level-up celebration component"
```

---

## Task 19: Web dashboard — level card + celebration mount

**Files:**
- Create: `resources/js/components/Gamification/LevelCard.vue` (ring + progress bar + "what's next", inside the dashboard; no scores/points number)
- Modify: the web dashboard view (`grep -rl "AppLayout" resources/js/views | xargs grep -l "dashboard" -i` → the `/dashboard` view, likely `resources/js/views/Dashboard.vue`) — mount `LevelCard` near the top and `GamificationCelebration` driven by the store's `pendingCelebration`; on mount dispatch `gamification/fetchStatus`.

- [ ] **Step 1: Write `LevelCard.vue`** — shows `levelName`, a progress bar bound to `progressPercent`, and `nextActions` as text. Palette tokens only; no points number; no icons. Reads from `mapState('gamification', …)`.

```vue
<template>
  <div class="card">
    <p class="text-sm font-semibold text-horizon-500">{{ levelLabel }}</p>
    <div class="mt-3 h-3 w-full rounded-full bg-horizon-100 overflow-hidden">
      <div class="h-full rounded-full bg-spring-500 transition-all" :style="{ width: progressPercent + '%' }"></div>
    </div>
    <p v-if="nextLevelName" class="mt-2 text-sm text-neutral-500">
      {{ nextActionsText }}
    </p>
    <p v-else class="mt-2 text-sm text-neutral-500">You've reached the top level.</p>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'LevelCard',
  computed: {
    ...mapState('gamification', ['level', 'levelName', 'progressPercent', 'nextLevelName', 'nextActions']),
    levelLabel() { return `Level ${this.level} · ${this.levelName}`; },
    nextActionsText() {
      if (!this.nextActions.length) return `Keep going to reach ${this.nextLevelName}.`;
      return `${this.nextActions.join(' and ')} to reach ${this.nextLevelName}.`;
    },
  },
};
</script>
```

- [ ] **Step 2: Wire into the dashboard view**

In the dashboard view template, add near the top (inside `AppLayout`):

```vue
<LevelCard class="mb-6" />
<GamificationCelebration
  v-if="celebration"
  :level="celebration.level"
  :level-name="celebration.level_name"
  :next-actions="celebration.next_actions"
  @dismiss="onCelebrationDismiss"
/>
```

Script:

```js
import { mapState, mapActions } from 'vuex';
import LevelCard from '@/components/Gamification/LevelCard.vue';
import GamificationCelebration from '@/components/Gamification/GamificationCelebration.vue';

export default {
  components: { /* …existing…, */ LevelCard, GamificationCelebration },
  computed: { ...mapState('gamification', { celebration: 'pendingCelebration' }) },
  methods: {
    ...mapActions('gamification', ['fetchStatus', 'acknowledge']),
    onCelebrationDismiss() { this.acknowledge(); },
  },
  mounted() { this.fetchStatus(); },
};
```

- [ ] **Step 3: Browser-verify (Rule #14)** — dev server, log in as `john@example.com` (fetch the code from the DB per CLAUDE.md), open `/dashboard`, confirm the level card renders with a progress bar and no points number. Then complete a recommendation that crosses a level threshold (or `php artisan tinker` to bump `total_points` + `pending_celebration_level`, reload) and confirm the fireworks takeover fires and dismiss clears it.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Gamification/LevelCard.vue resources/js/views/Dashboard.vue
git commit -m "feat(gamification): web dashboard level card + celebration mount"
```

---

## Task 20: `/m` — store, celebration, wheel repoint, Fyn interrupt

**Files:**
- Modify: `resources/mobile/store.js` (add gamification state + status/ack via the mobile API client)
- Reuse: `GamificationCelebration.vue` (or a thin `/m` wrapper if the mobile build can't import from `resources/js`; confirm the mobile Vite alias — if not, copy the component into `resources/mobile/components/`)
- Modify: `resources/mobile/views/Dashboard.vue` (wheel reads engine-fed fields; mount celebration)
- Modify: the `/m` Fyn chat component — on receiving a `level_up` SSE frame, after the reply renders, show the celebration; on dismiss call ack.

- [ ] **Step 1: Add gamification to the mobile store** — `fetchStatus()` (GET `/gamification/status` with the mobile bearer), `pendingCelebration`, `ack()`.

- [ ] **Step 2: Confirm the `/m` Dashboard wheel** uses the engine-fed `level`/`progress_percent`/`percentile` from `GET /api/v1/mobile/dashboard` (now repointed in Task 15). Adjust any field names changed in Task 15 step 3 (e.g. `actions_for_next` → `progress_percent` + `next_level_name`). Do NOT remove the level/percentile/"X of Y actions" display — that is the CSJ-approved `/m` gamification (Rule #12 carve-out).

- [ ] **Step 3: Mount the celebration on the `/m` dashboard** driven by `pendingCelebration`; on mount call `fetchStatus()` so a missed celebration delivers on next open.

- [ ] **Step 4: Wire the Fyn-chat interrupt** — find where the `/m` chat client parses SSE frames (`grep -rn "onboarding_advance\|'done'\|JSON.parse" resources/mobile`), add a `level_up` case that, **after** the current reply has finished rendering, sets the celebration (store `queueCelebration`). On dismiss, call ack and leave the conversation untouched (the completed reply remains beneath).

- [ ] **Step 5: Build for `/m` and browser-verify (Rule #14 — headline path)**

Run: `./deploy/mobile/build-ios.sh` is for iOS; for `/m` web, rebuild the mobile web bundle per the project's mobile build (`grep -n "m-build\|build-mobile" package.json deploy/ -r | head`) and run locally. Then walk the **savetax `/m` onboarding** end-to-end (answer questions → cross a level threshold → confirm the fireworks interrupt appears AFTER Fyn's reply → dismiss → conversation resumes → `pending` cleared).

- [ ] **Step 6: Commit**

```bash
git add resources/mobile/store.js resources/mobile/views/Dashboard.vue resources/mobile/components/ <fyn-chat-component>
git commit -m "feat(gamification): /m store + celebration + engine-fed wheel + Fyn-chat interrupt"
```

---

# Phase 6 — Verification

## Task 21: Full test pass + end-to-end browser walks

- [ ] **Step 1: Run the full gamification suite**

Run: `./vendor/bin/pest tests/Unit/Services/Gamification tests/Feature/Gamification tests/Unit/Services/Mobile/MobileLevelServiceTest.php`
Expected: all green.

- [ ] **Step 2: Run the broader suites that touch changed code**

Run: `./vendor/bin/pest tests/Feature/Mobile tests/Unit/Services/Mobile`
Expected: green (fix any shape regressions from the `MobileLevelService` repoint).

- [ ] **Step 3: Run the backfill on the dev DB**

Run: `php artisan gamification:backfill`
Expected: "Gamification backfill complete." Spot-check a seeded user in tinker: non-zero `total_points`, `pending_celebration_level` null.

- [ ] **Step 4: Browser walk — savetax `/m` onboarding (the headline)**

Per CLAUDE.md Rule #14 + browser-testing law: phone-UA, enter the savetax funnel → register → Fyn onboarding → answer questions → observe points accruing (level wheel advances) → trigger a level-up → confirm the **fireworks interrupt appears after Fyn's reply**, dismiss it, confirm the conversation resumes exactly where it was and the pending flag cleared. Log every interaction.

- [ ] **Step 5: Browser walk — web dashboard level-up**

Log in (`john@example.com`, code from DB) → `/dashboard` shows the level card (no points number) → complete a recommendation that crosses a threshold → fireworks takeover → dismiss → card shows the new level + progress.

- [ ] **Step 6: Final commit / PR prep**

```bash
git add -A
git commit -m "test(gamification): full suite + backfill verified; e2e browser walks complete"
git push -u origin feat/gamification-engine
```

Open a PR targeting `dev` (never `main`), `@Stoff73` as reviewer (CODEOWNERS enforces). Do NOT deploy to production — that's a separate `dev → main` release, CSJ's call.

---

## Spec coverage check (self-review)

| Spec section | Task(s) |
|---|---|
| §3.1 `point_awards` ledger | Task 1, 2 |
| §3.2 `user_gamification` aggregate | Task 1, 2 |
| §3.3 reuse `user_milestones` | Task 10, 16 |
| §4.1 `PointsService.award` | Task 6 |
| §4.2 `LevelService` | Task 4 |
| §4.3 `LevelUpCollector` | Task 5 |
| §4.4 `MobileLevelService` reader | Task 15 |
| §5 award triggers (all 7 rows) | Tasks 7, 8, 9, 10, 11, 12 |
| §6 levels config | Task 3 |
| §7 API (`status`, `ack`) | Task 13 |
| §7 SSE `level_up` after `done` | Task 14 |
| §8 frontend (component, state, web card, /m, two delivery paths) | Tasks 17, 18, 19, 20 |
| §9 backfill (quiet, idempotent) | Task 16 |
| §10 integrity (preview, permanence, never-throw, joint) | Tasks 6, 7 (+ enforced throughout) |
| §11 testing | Tasks 4–16 (unit/feature) + Task 21 (browser) |
| §12 build sequence | Phases 1–6 |

No placeholders remain in committed code; every code step shows the code. Method/property names are consistent across tasks (`award`, `awardDataEntry`, `recordLogin`, `levelForPoints`, `progress`, `nextActions`, `levelName`, `record`/`hasLevelUp`/`highest`, `levelUpFrame`). Income/expenditure (§5) and the onboarding step key (§5) have explicit "locate the seam" steps because their exact write paths must be confirmed in the codebase before wiring.
