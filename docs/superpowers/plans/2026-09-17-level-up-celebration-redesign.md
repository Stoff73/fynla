# Level-up celebration redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the full-screen level-up takeover with a banked, dashboard-only animation: the level number climbs one level at a time inside the hero circle, with a confetti burst out of the circle at each increment.

**Architecture:** The server stops storing "one pending level" and instead stores the highest level the user has *seen* celebrated (`celebrated_level`), exposing what is owed as a range (`celebrate_from` / `celebrate_to`). Each client gets one small pure module holding the sequence rule — which levels are owed, pacing, reduced-motion, and the view predicate — and the dashboard hero drives the animation from it. The three full-screen overlay components and all five of their trigger sites are deleted.

**Tech Stack:** Laravel 10 / Pest (server), Vue 3 + Vuex + Vite / Vitest (web), Vue 3 + isolated Vite bundle / Vitest (`/m`), SwiftUI / XCTest (native iOS).

**Spec:** `docs/superpowers/specs/2026-09-17-level-up-celebration-redesign-design.md` — read it before Task 1. The plan argues from the spec; where they disagree, the spec wins.

## Global Constraints

- `declare(strict_types=1);` at the top of every PHP file.
- **NEVER** `migrate:fresh` / `migrate:refresh` / `--env=testing`. Run `php artisan db:seed` after anything that loses local data.
- **No amber or orange.** Warnings → `violet-*`, errors → `raspberry-*`, success → `spring-*`. Confetti uses spring / raspberry / violet only.
- **No hex in `<style>`** — `@apply` with palette tokens (`raspberry/horizon/spring/violet/savannah/eggshell/neutral/light-*`). Never `primary-*`/`secondary-*`/`gray-*`.
- **No icons on the dashboard** (Rule 15 banned surface) and **no emoji or Unicode-as-icons anywhere** — not in strings, comments, commit messages or test names.
- **No scores in user-facing UI** (Rule 12). The level wheel, "X of Y actions complete" and percentile are an explicit CSJ-approved carve-out: **do not remove or alter them.** Only the celebration that fires over them changes.
- User-facing text is British (Optimisation, Customise); code identifiers are American (`optimize`, `center`).
- Currency via `currencyMixin` — not relevant here, but never add a local `formatCurrency()`.
- Rule 19: "done" means verified on web **and** `/m`. Native is in scope for this plan too (CSJ 2026-09-17).
- Branch off `dev`, never `main`. PRs target `dev`.

---

### Task 1: Server — the celebration becomes a range

**Files:**
- Create: `database/migrations/2026_09_17_120000_replace_pending_celebration_with_celebrated_level.php`
- Modify: `app/Models/UserGamification.php:15-22`
- Modify: `app/Http/Controllers/Api/GamificationController.php:18-52`
- Modify: `app/Services/Gamification/PointsService.php:60-62`
- Modify: `app/Console/Commands/GamificationBackfill.php:34, 101`
- Test: `tests/Feature/Gamification/GamificationApiTest.php` (modify), `tests/Feature/Gamification/CelebrationRangeTest.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: `GET /api/gamification/status` returns `celebrate_from: int` and `celebrate_to: int` (and no longer `pending_celebration`). `POST /api/gamification/celebration/ack` accepts optional `{ "level": int }` and returns `{ "acknowledged": true, "celebrated_level": int }`. `UserGamification::$celebrated_level` (nullable int).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Gamification/CelebrationRangeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use Laravel\Sanctum\Sanctum;

/**
 * The celebration is a RANGE, not a single pending level — the level number
 * climbs one at a time on the dashboard, so the server must be able to say
 * "you are owed 4, 5 and 6" (CSJ 2026-09-17).
 */
it('owes nothing when the celebrated level has caught up with the real level', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 290, 'level' => 4, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 4)
        ->assertJsonPath('celebrate_to', 4);
});

it('owes every level above the one last celebrated', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 4)
        ->assertJsonPath('celebrate_to', 7);
});

/**
 * The null case MUST coalesce to 1, never to the current level. Coalescing to
 * the level would mean a brand-new user who reaches level 2 before ever
 * acking has from == to, and is never celebrated at all.
 */
it('owes a brand new user the level they just reached', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 60, 'level' => 2, 'celebrated_level' => null,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 1)
        ->assertJsonPath('celebrate_to', 2);
});

it('banks the acknowledged level so the climb never replays', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack', ['level' => 7])
        ->assertOk()
        ->assertJsonPath('acknowledged', true)
        ->assertJsonPath('celebrated_level', 7);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 7)
        ->assertJsonPath('celebrate_to', 7);
});

it('is idempotent — a second ack cannot push the celebrated level past the real one', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack', ['level' => 7])->assertOk();
    $this->postJson('/api/gamification/celebration/ack', ['level' => 99])
        ->assertOk()
        ->assertJsonPath('celebrated_level', 7);

    expect(UserGamification::where('user_id', $user->id)->value('celebrated_level'))->toBe(7);
});

it('never lowers the celebrated level on a late or out of order ack', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 6,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack', ['level' => 5])
        ->assertOk()
        ->assertJsonPath('celebrated_level', 6);
});

it('acks everything owed when no level is given', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack')
        ->assertOk()
        ->assertJsonPath('celebrated_level', 7);
});

/**
 * THE PRODUCTION SAFETY TEST. Every existing row is migrated as
 * already-celebrated. Without it, a live user sitting at level 6 gets a
 * five-step climb the next time they open the dashboard.
 */
it('owes nothing to a user who existed before this feature', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 7,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 7)
        ->assertJsonPath('celebrate_to', 7);
});
```

- [ ] **Step 2: Run the tests and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Gamification/CelebrationRangeTest.php`
Expected: FAIL — `celebrated_level` is not a column, so `UserGamification::create` throws, and `celebrate_from` is absent from the response.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_09_17_120000_replace_pending_celebration_with_celebrated_level.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The celebration was a single pending level, so a user who crossed three
 * levels at once could only ever be shown the last. It becomes "the highest
 * level already celebrated", and what is owed is the range above it.
 *
 * The backfill is the whole point of the up() ordering: every existing user
 * is marked as already celebrated to their current level. Without it, a live
 * user at level 6 gets a five-step climb on their next dashboard view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->unsignedInteger('celebrated_level')->nullable()->after('level');
        });

        DB::table('user_gamification')->update(['celebrated_level' => DB::raw('`level`')]);

        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->dropColumn('pending_celebration_level');
        });
    }

    public function down(): void
    {
        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->unsignedInteger('pending_celebration_level')->nullable()->after('level');
        });

        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->dropColumn('celebrated_level');
        });
    }
};
```

- [ ] **Step 4: Update the model**

In `app/Models/UserGamification.php`, replace `'pending_celebration_level'` with `'celebrated_level'` in both `$fillable` (:15) and `$casts` (:22). The cast stays `'integer'`.

- [ ] **Step 5: Update the controller**

In `app/Http/Controllers/Api/GamificationController.php`, replace the `$pending` block (:26-32) and the `pending_celebration` key (:42), and rewrite `ackCelebration()` (:46-52):

```php
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $g = UserGamification::firstOrCreate(['user_id' => $user->id]);

        $progress = $this->levels->progress((int) $g->total_points);
        $nextActions = $this->levels->nextActions($user);

        // The ladder starts at 1, so a user who has never celebrated anything
        // is owed everything above 1. NEVER coalesce to the current level —
        // a new user who reaches level 2 before acking would then have
        // from == to and never be celebrated at all.
        $from = (int) ($g->celebrated_level ?? 1);
        $to = (int) $progress['level'];

        return response()->json([
            'level' => $progress['level'],
            'level_name' => $progress['level_name'],
            'level_label' => $progress['level_label'],
            'progress_percent' => $progress['progress_percent'],
            'next_level_name' => $progress['next_level_name'],
            'next_actions' => $nextActions,
            'celebrate_from' => min($from, $to),
            'celebrate_to' => $to,
        ]);
    }

    public function ackCelebration(Request $request): JsonResponse
    {
        $user = $request->user();
        $g = UserGamification::firstOrCreate(['user_id' => $user->id]);

        $real = (int) $g->level;
        $asked = $request->integer('level') ?: $real;

        // Monotonic and clamped: a double ack, an out-of-order ack, or an ack
        // for a level the user has not reached can never move this backwards
        // or past the truth.
        $g->celebrated_level = min($real, max((int) ($g->celebrated_level ?? 1), $asked));
        $g->save();

        return response()->json([
            'acknowledged' => true,
            'celebrated_level' => (int) $g->celebrated_level,
        ]);
    }
```

- [ ] **Step 6: Stop PointsService writing a pending level**

In `app/Services/Gamification/PointsService.php`, delete lines 60-62:

```php
                if ($leveledUp) {
                    $g->pending_celebration_level = $newLevel;
                }
```

Leave `$g->level = $newLevel;` and the `UserLevelCrossing` block (:65-72) exactly as they are. `celebrated_level` moves only on ack.

- [ ] **Step 7: Update the backfill command**

In `app/Console/Commands/GamificationBackfill.php:101`, replace the quieting update:

```php
                UserGamification::where('user_id', $user->id)->update(['celebrated_level' => DB::raw('`level`')]);
```

Update the docblock at :34 to say `celebrated_level is set to the earned level` rather than `pending_celebration_level is cleared`. Add `use Illuminate\Support\Facades\DB;` if it is not already imported.

- [ ] **Step 8: Fix the two existing API tests**

In `tests/Feature/Gamification/GamificationApiTest.php`: change `assertJsonStructure(['next_actions', 'pending_celebration'])` at :20 to `assertJsonStructure(['next_actions', 'celebrate_from', 'celebrate_to'])`, and delete the whole `it('surfaces a pending celebration then clears it on ack', ...)` block (:23-36) — `CelebrationRangeTest.php` covers it properly now. Then grep for other references:

```bash
grep -rn "pending_celebration" tests/ app/
```

Expected after this task: no matches outside the migration's `down()`.

- [ ] **Step 9: Run the migration and the tests**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Gamification tests/Unit/Services/Gamification
```

Expected: PASS. If local data was lost for any reason, `php artisan db:seed`.

- [ ] **Step 10: Format and commit**

```bash
./vendor/bin/pint app/ database/migrations/ tests/
git add app database tests
git commit -m "feat(gamification): the celebration is a range, not one pending level

A user who crossed three levels at once could only ever be shown the last,
because the server held a single pending_celebration_level. It now holds
celebrated_level - the highest level the user has actually seen - and status
returns celebrate_from/celebrate_to so the client can climb the range.

The migration backfills every existing row as already-celebrated. Without
that, a live user at level 6 gets a five-step climb on their next dashboard
view. It has its own test."
```

---

### Task 2: Web — the sequence module

**Files:**
- Create: `resources/js/utils/levelCelebration.js`
- Test: `resources/js/utils/__tests__/levelCelebration.spec.js`

**Interfaces:**
- Consumes: the `celebrate_from` / `celebrate_to` contract from Task 1.
- Produces, imported by Task 3 and mirrored byte-for-byte in behaviour by Tasks 4 and 5:
  - `levelsOwed(from: number, to: number): number[]`
  - `dashboardIsBeingViewed({ onDashboard: boolean, fynOpen: boolean, hidden: boolean }): boolean`
  - `prefersReducedMotion(): boolean`
  - `runLevelSequence({ from, to, onLevel, reducedMotion, stepMs, wait }): Promise<number|null>` — calls `onLevel(level, { burst })` per step, resolves with the level to ack, or `null` when nothing was owed.
  - `STEP_MS: number` (900)

- [ ] **Step 1: Write the failing test**

Create `resources/js/utils/__tests__/levelCelebration.spec.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import {
  levelsOwed,
  dashboardIsBeingViewed,
  runLevelSequence,
  STEP_MS,
} from '@/utils/levelCelebration';

describe('levelsOwed', () => {
  it('owes nothing when the range is empty', () => {
    expect(levelsOwed(4, 4)).toEqual([]);
  });

  it('owes every level above the one last celebrated', () => {
    expect(levelsOwed(3, 6)).toEqual([4, 5, 6]);
  });

  it('owes a single level for a single crossing', () => {
    expect(levelsOwed(1, 2)).toEqual([2]);
  });

  it('owes nothing when the server sends a range that runs backwards', () => {
    expect(levelsOwed(6, 3)).toEqual([]);
  });

  it('treats missing values as the bottom of the ladder', () => {
    expect(levelsOwed(undefined, 2)).toEqual([2]);
    expect(levelsOwed(null, null)).toEqual([]);
  });
});

describe('dashboardIsBeingViewed', () => {
  it('is true on the dashboard with Fyn closed and the tab visible', () => {
    expect(dashboardIsBeingViewed({ onDashboard: true, fynOpen: false, hidden: false })).toBe(true);
  });

  it('is false while Fyn is over the dashboard', () => {
    expect(dashboardIsBeingViewed({ onDashboard: true, fynOpen: true, hidden: false })).toBe(false);
  });

  it('is false away from the dashboard', () => {
    expect(dashboardIsBeingViewed({ onDashboard: false, fynOpen: false, hidden: false })).toBe(false);
  });

  it('is false when the tab is backgrounded, so the climb is never spent unseen', () => {
    expect(dashboardIsBeingViewed({ onDashboard: true, fynOpen: false, hidden: true })).toBe(false);
  });
});

describe('runLevelSequence', () => {
  it('does nothing and acks nothing when no level is owed', async () => {
    const onLevel = vi.fn();
    const acked = await runLevelSequence({ from: 5, to: 5, onLevel, wait: () => Promise.resolve() });

    expect(onLevel).not.toHaveBeenCalled();
    expect(acked).toBeNull();
  });

  it('steps through every owed level in order, bursting at each', async () => {
    const onLevel = vi.fn();
    const acked = await runLevelSequence({ from: 3, to: 6, onLevel, wait: () => Promise.resolve() });

    expect(onLevel.mock.calls.map((c) => c[0])).toEqual([4, 5, 6]);
    expect(onLevel.mock.calls.every((c) => c[1].burst === true)).toBe(true);
    expect(acked).toBe(6);
  });

  it('waits between levels so the climb is legible', async () => {
    const wait = vi.fn(() => Promise.resolve());
    await runLevelSequence({ from: 3, to: 5, onLevel: () => {}, wait });

    expect(wait).toHaveBeenCalledTimes(2);
    expect(wait).toHaveBeenCalledWith(STEP_MS);
  });

  it('under reduced motion lands on the final level with no burst, and still acks', async () => {
    const onLevel = vi.fn();
    const wait = vi.fn(() => Promise.resolve());
    const acked = await runLevelSequence({
      from: 3, to: 6, onLevel, reducedMotion: true, wait,
    });

    expect(onLevel).toHaveBeenCalledTimes(1);
    expect(onLevel).toHaveBeenCalledWith(6, { burst: false });
    expect(wait).not.toHaveBeenCalled();
    expect(acked).toBe(6);
  });
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `npx vitest run resources/js/utils/__tests__/levelCelebration.spec.js`
Expected: FAIL — `Failed to resolve import "@/utils/levelCelebration"`.

- [ ] **Step 3: Write the module**

Create `resources/js/utils/levelCelebration.js`:

```js
/**
 * The level-up celebration rule, in one place.
 *
 * The full-screen takeover was removed on 2026-09-17: level-ups are banked
 * server-side as a range and spent only on the dashboard hero circle, where
 * the number climbs one level at a time with a confetti burst per increment.
 *
 * `/m` and native iOS each carry their own copy of this rule — separate Vite
 * bundles cannot import each other and native is Swift — so the three are
 * kept honest by the same test vectors, not by a shared import. If you change
 * behaviour here, change it in `resources/mobile/navigation/levelCelebration.js`
 * and `ios-native/Fynla/Features/Gamification/LevelCelebrationSequence.swift`
 * in the same commit.
 */

/** Pause between level steps, in milliseconds. Three levels lands under 3s. */
export const STEP_MS = 900;

/**
 * Every level owed, ascending. The ladder starts at 1, so a missing `from`
 * means "never celebrated anything".
 *
 * @param {number|null|undefined} from
 * @param {number|null|undefined} to
 * @returns {number[]}
 */
export function levelsOwed(from, to) {
  const start = Number.isFinite(from) ? Number(from) : 1;
  const end = Number.isFinite(to) ? Number(to) : 1;
  if (end <= start) return [];
  return Array.from({ length: end - start }, (_, i) => start + i + 1);
}

/**
 * The dashboard is what the user is actually looking at: it is the active
 * screen, Fyn is not over it, and the tab is in front. A backgrounded tab
 * matters — the climb would otherwise be spent and acked unseen.
 */
export function dashboardIsBeingViewed({ onDashboard, fynOpen, hidden }) {
  return Boolean(onDashboard) && !fynOpen && !hidden;
}

/** The viewer's operating-system "reduce motion" accessibility setting. */
export function prefersReducedMotion() {
  try {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  } catch {
    return false;
  }
}

const defaultWait = (ms) => new Promise((resolve) => { setTimeout(resolve, ms); });

/**
 * Walk the owed levels, calling `onLevel(level, { burst })` at each.
 *
 * Under reduced motion the number simply lands on the final level with no
 * burst — but the sequence STILL resolves with a level to ack, or the user
 * accumulates a queue they can never spend.
 *
 * @returns {Promise<number|null>} the level to acknowledge, or null if nothing was owed
 */
export async function runLevelSequence({
  from,
  to,
  onLevel,
  reducedMotion = false,
  stepMs = STEP_MS,
  wait = defaultWait,
}) {
  const levels = levelsOwed(from, to);
  if (levels.length === 0) return null;

  const last = levels[levels.length - 1];

  if (reducedMotion) {
    onLevel(last, { burst: false });
    return last;
  }

  for (const level of levels) {
    onLevel(level, { burst: true });
    await wait(stepMs);
  }

  return last;
}
```

- [ ] **Step 4: Run the test and watch it pass**

Run: `npx vitest run resources/js/utils/__tests__/levelCelebration.spec.js`
Expected: PASS, 14 tests.

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/levelCelebration.js resources/js/utils/__tests__/levelCelebration.spec.js
git commit -m "feat(gamification): the level-up sequence rule, in one module

Which levels are owed, the pacing, the reduced-motion short circuit and the
'is the user actually looking at the dashboard' predicate. /m and native
carry their own copy of the same rule - separate bundles, and Swift - so the
three are held together by shared test vectors rather than a shared import."
```

---

### Task 3: Web — the dashboard circle animates, and the takeover goes

**Files:**
- Modify: `resources/js/views/GamifiedDashboard.vue:27-40` (the circle) and its script
- Modify: `resources/js/views/gamified-dashboard.css` (burst + number styles)
- Modify: `resources/js/views/Dashboard.vue:3-8, 138-148, 756-757` (remove the overlay mount)
- Modify: `resources/js/store/modules/gamification.js` (whole file)
- Modify: `resources/js/store/modules/aiChat.js:935, 1174, 1444`
- Delete: `resources/js/components/Gamification/GamificationCelebration.vue`
- Test: `resources/js/utils/__tests__/levelCelebration.spec.js` (from Task 2, unchanged)

**Interfaces:**
- Consumes: `levelsOwed`, `dashboardIsBeingViewed`, `prefersReducedMotion`, `runLevelSequence` from Task 2; `celebrate_from` / `celebrate_to` from Task 1.
- Produces: the store shape `gamification.celebrateFrom: number`, `gamification.celebrateTo: number`, action `gamification/acknowledge(level)`. Task 4 mirrors this shape on `/m`.

- [ ] **Step 1: Harvest the confetti before deleting the component**

Open `resources/js/components/Gamification/GamificationCelebration.vue` and read `buildConfetti()` (:55-63) and the `.confetti` CSS. That generation logic moves into the dashboard; `buildFireworks()` and `particleStyle()` belong to the fireworks and are **not** needed. Do not write new confetti from scratch.

- [ ] **Step 2: Rewrite the store module**

Replace the whole of `resources/js/store/modules/gamification.js`:

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
    // The banked climb: every level above celebrateFrom, up to celebrateTo,
    // is owed and will be spent on the dashboard hero circle.
    celebrateFrom: 1,
    celebrateTo: 1,
  }),
  mutations: {
    SET_STATUS(state, p) {
      state.level = p.level;
      state.levelName = p.level_name;
      state.progressPercent = p.progress_percent;
      state.nextLevelName = p.next_level_name;
      state.nextActions = p.next_actions || [];
      state.celebrateFrom = p.celebrate_from ?? p.level ?? 1;
      state.celebrateTo = p.celebrate_to ?? p.level ?? 1;
    },
    SETTLE_CELEBRATION(state, level) {
      state.celebrateFrom = level;
      state.celebrateTo = Math.max(level, state.celebrateTo);
    },
  },
  actions: {
    async fetchStatus({ commit }) {
      const { data } = await gamificationService.status();
      commit('SET_STATUS', data);
      return data;
    },
    async acknowledge({ commit }, level) {
      commit('SETTLE_CELEBRATION', level);
      try { await gamificationService.ackCelebration(level); } catch (e) { /* non-fatal */ }
    },
  },
};
```

And in `resources/js/services/gamification.js`, pass the level:

```js
  ackCelebration(level) {
    return api.post('/gamification/celebration/ack', level ? { level } : {});
  },
```

- [ ] **Step 3: Remove the three SSE dispatches**

In `resources/js/store/modules/aiChat.js` at :935, :1174 and :1444, replace each `case 'level_up':` body with a no-op that explains itself:

```js
                            case 'level_up':
                                // Deliberately ignored. Level-ups are banked
                                // server-side and spent on the dashboard hero
                                // circle; nothing may interrupt a Fyn turn
                                // (CSJ 2026-09-17). The frame stays on the
                                // wire for older clients.
                                break;
```

- [ ] **Step 4: Remove the overlay mount**

In `resources/js/views/Dashboard.vue`: delete the `<GamificationCelebration>` block (:3-8), the import (:138), the component registration (:147), and the `...mapState('gamification', { celebration: 'pendingCelebration' })` line (:757). Leave `<GamifiedDashboard />` at :110 and the status refresh at :1358 alone.

Then delete the component:

```bash
git rm resources/js/components/Gamification/GamificationCelebration.vue
```

- [ ] **Step 5: Animate the circle**

In `resources/js/views/GamifiedDashboard.vue`, the circle block (:27-40) becomes — note `displayLevel` replaces `level` in the number, and the burst layer is `aria-hidden` so the climb is silent to assistive tech (the card's existing `aria-label` already states the level):

```html
          <section class="md-level" aria-labelledby="gdm-level-h">
            <div class="md-level__pie" role="img" :aria-label="`Level ${displayLevel}, ${ringPercent} percent complete`">
              <svg class="md-level__pie-svg" viewBox="0 0 100 100" aria-hidden="true">
                <circle class="md-level__pie-track" cx="50" cy="50" r="44" />
                <circle class="md-level__pie-arc" cx="50" cy="50" r="44" :style="{ '--progress': ringPercent }" />
              </svg>
              <span v-if="burst" class="md-level__burst" aria-hidden="true">
                <i v-for="c in confetti" :key="c.id" :style="c.style"></i>
              </span>
              <div class="md-level__pie-inner">
                <p class="md-level__pie-label">Level</p>
                <p class="md-level__pie-num" :class="{ 'is-stepping': stepping }">{{ displayLevel }}</p>
              </div>
            </div>
```

In the script, add to `data()`:

```js
      displayLevel: null,   // null until the climb decides; falls back to level
      ringPercent: 0,
      stepping: false,
      burst: false,
      confetti: [],
      celebrating: false,
```

Add the imports and methods:

```js
import {
  dashboardIsBeingViewed,
  prefersReducedMotion,
  runLevelSequence,
} from '@/utils/levelCelebration';

const CONFETTI_COLOURS = ['var(--spring-500)', 'var(--raspberry-500)', 'var(--violet-400)'];

// ... in methods:
    buildConfetti() {
      // 18 pieces thrown radially from the circle's edge. Ported from the
      // deleted GamificationCelebration.vue, retargeted from full-screen to
      // an origin at the circle.
      return Array.from({ length: 18 }, (_, i) => {
        const angle = (360 / 18) * i + (Math.random() * 12 - 6);
        const distance = 70 + Math.random() * 50;
        return {
          id: `${Date.now()}-${i}`,
          style: {
            '--angle': `${angle}deg`,
            '--distance': `${distance}px`,
            '--delay': `${Math.random() * 90}ms`,
            background: CONFETTI_COLOURS[i % CONFETTI_COLOURS.length],
          },
        };
      });
    },

    async playBankedLevels() {
      if (this.celebrating) return;

      const owed = this.celebrateTo > this.celebrateFrom;
      if (!owed) {
        this.displayLevel = this.level;
        this.ringPercent = this.progressPercent;
        return;
      }

      if (!dashboardIsBeingViewed({
        onDashboard: true,
        fynOpen: this.$store.getters['aiChat/isOpen'],
        hidden: document.hidden,
      })) return;

      this.celebrating = true;
      // Start the climb from where they were last celebrated.
      this.displayLevel = this.celebrateFrom;

      const acked = await runLevelSequence({
        from: this.celebrateFrom,
        to: this.celebrateTo,
        reducedMotion: prefersReducedMotion(),
        onLevel: (level, { burst }) => {
          this.displayLevel = level;
          this.stepping = true;
          setTimeout(() => { this.stepping = false; }, 260);
          if (!burst) { this.ringPercent = this.progressPercent; return; }
          this.confetti = this.buildConfetti();
          this.burst = true;
          setTimeout(() => { this.burst = false; }, 800);
          // Sweep the ring full, then snap it back ready for the next level.
          this.ringPercent = 100;
          setTimeout(() => {
            this.ringPercent = level === this.celebrateTo ? this.progressPercent : 0;
          }, 420);
        },
      });

      this.celebrating = false;
      if (acked !== null) await this.$store.dispatch('gamification/acknowledge', acked);
    },
```

Add a computed `displayLevel` fallback — because `displayLevel` starts null:

```js
    // In computed: the number the circle shows. Before and after a climb this
    // is simply the real level; during one it is whatever step we are on.
    shownLevel() {
      return this.displayLevel === null ? this.level : this.displayLevel;
    },
```

…and use `shownLevel` in the template instead of `displayLevel` (rename both template usages). Keep the `data()` key named `displayLevel`.

Wire the watchers in `mounted()` and a `watch` block:

```js
  watch: {
    celebrateTo() { this.playBankedLevels(); },
    '$store.state.aiChat.isOpen'(open) { if (!open) this.playBankedLevels(); },
  },
  mounted() {
    this.ringPercent = this.progressPercent;
    document.addEventListener('visibilitychange', this.onVisibility);
    this.playBankedLevels();
  },
  beforeUnmount() {
    document.removeEventListener('visibilitychange', this.onVisibility);
  },
```

with `onVisibility() { if (!document.hidden) this.playBankedLevels(); }` in methods.

- [ ] **Step 6: Style the burst**

Append to `resources/js/views/gamified-dashboard.css` — palette tokens only, no hex.

**Every selector in that file is prefixed `.gamified-dash`** (see :103-112). An unprefixed rule either loses on specificity or ties and depends on source order — prefix all of them, as below.

**Do not add a `stroke-dashoffset` transition**: `.gamified-dash .md-level__pie-arc` at :106 already has `transition: stroke-dashoffset 0.45s cubic-bezier(0.4,0,0.2,1)`. The sweep in Step 5 reuses it, which is why the snap-back timeout is 420ms — just under that 450ms so each level's sweep completes before the next begins.

`.gamified-dash .md-level__pie` at :103 is already `position: relative`, so the absolutely-positioned burst anchors to the circle without further change.

```css
/* Level-up burst: confetti thrown radially from the level circle. Replaces
   the full-screen takeover removed on 2026-09-17. */
.gamified-dash .md-level__burst {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: visible;
}

.gamified-dash .md-level__burst i {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 7px;
  height: 11px;
  border-radius: 1px;
  opacity: 0;
  animation: md-level-confetti 800ms ease-out var(--delay) forwards;
}

@keyframes md-level-confetti {
  0%   { opacity: 1; transform: rotate(var(--angle)) translateY(-46px) rotate(0deg) scale(0.6); }
  60%  { opacity: 1; }
  100% { opacity: 0; transform: rotate(var(--angle)) translateY(calc(-46px - var(--distance))) rotate(420deg) scale(1); }
}

.gamified-dash .md-level__pie-num {
  transition: transform 260ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.gamified-dash .md-level__pie-num.is-stepping {
  transform: scale(1.28);
}

@media (prefers-reduced-motion: reduce) {
  .gamified-dash .md-level__burst { display: none; }
  .gamified-dash .md-level__pie-num { transition: none; }
  .gamified-dash .md-level__pie-num.is-stepping { transform: none; }
  .gamified-dash .md-level__pie-arc { transition: none; }
}
```

- [ ] **Step 7: Run the suites**

```bash
npx vitest run
./vendor/bin/pest tests/Feature/Gamification
```

Expected: PASS. Fix any spec that referenced the deleted component.

- [ ] **Step 8: Browser-verify on web**

This is the gate. A snapshot is not a test — click, watch, assert.

```bash
./dev.sh
php artisan tinker --execute="\$u = \App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code ?? 'none';"
```

Bank two levels for john without touching the dashboard:

```bash
php artisan tinker --execute="\$u=\App\Models\User::where('email','john@example.com')->first(); \$g=\App\Models\UserGamification::firstOrCreate(['user_id'=>\$u->id]); \$g->level=\$g->level+2; \$g->save(); echo 'level '.\$g->level.' celebrated '.var_export(\$g->celebrated_level,true);"
```

Then in Playwright at `http://localhost:8000`:
1. Open Fyn on the dashboard and send a message. **Assert nothing animates during the turn.**
2. Collapse Fyn. Assert the number climbs two steps and confetti appears at each.
3. Reload. Assert the number is static at the new level and does **not** replay.
4. Set Reduce Motion (`page.emulateMedia({ reducedMotion: 'reduce' })`), bank another level, reload: assert the number is correct with no animation, and that it does not replay on a second reload.

- [ ] **Step 9: Commit**

```bash
git add resources/js
git commit -m "feat(dashboard): the level climbs in the hero circle instead of taking over the screen

The full-screen celebration is deleted along with its three SSE triggers, so
nothing can interrupt a Fyn conversation. Banked levels are spent on the
dashboard hero circle: the number steps one level at a time, confetti sprays
from the circle at each step, and the ring sweeps and resets per level.
Honours prefers-reduced-motion, which still acks so no queue accumulates."
```

---

### Task 4: `/m` — the same behaviour on mobile web

**Files:**
- Create: `resources/mobile/navigation/levelCelebration.js`
- Create: `resources/mobile/navigation/__tests__/levelCelebration.spec.js`
- Modify: `resources/mobile/views/Dashboard.vue:29-45` and its script
- Modify: `resources/mobile/style.css`
- Modify: `resources/mobile/store.js:10-20, 53-92`
- Modify: `resources/mobile/mixins/onboardingChat.js:70, 197, 363, 693`
- Delete: `resources/mobile/components/GamificationCelebration.vue`
- Test: `resources/mobile/views/__tests__/Dashboard.spec.js` (modify)

**Interfaces:**
- Consumes: Task 1's contract. Mirrors Task 2's module behaviour exactly.
- Produces: `store.gamification.celebrateFrom` / `celebrateTo`, `store.ackCelebration(level)`.

- [ ] **Step 1: Copy the module and its test**

`resources/mobile/navigation/levelCelebration.js` is a verbatim copy of `resources/js/utils/levelCelebration.js` from Task 2 — same functions, same behaviour, same `STEP_MS`. Change only the docblock's cross-reference line so it points back at the web copy and the Swift copy.

`resources/mobile/navigation/__tests__/levelCelebration.spec.js` is a verbatim copy of the Task 2 spec with the import path changed to `../levelCelebration.js`. **Do not thin the vectors** — they are what keeps the three implementations honest.

- [ ] **Step 2: Run the copied test and watch it pass**

Run: `npx vitest run resources/mobile/navigation/__tests__/levelCelebration.spec.js`
Expected: PASS, 14 tests.

- [ ] **Step 3: Update the `/m` store**

In `resources/mobile/store.js`: replace `pendingCelebration: null` (:20) and its comment (:10-12) with `celebrateFrom: 1, celebrateTo: 1` on the `gamification` object. In `fetchStatus()` (:53-75) replace the `if (d.pending_celebration)` block (:66-72) with:

```js
      this.gamification.celebrateFrom = d.celebrate_from ?? d.level ?? 1;
      this.gamification.celebrateTo = d.celebrate_to ?? d.level ?? 1;
```

Delete `queueCelebration()` (:77-85) entirely. Replace `ack()` (:86-92) with:

```js
  // Bank the climb the dashboard has just shown so it never replays.
  async ackCelebration(level) {
    this.gamification.celebrateFrom = level;
    this.gamification.celebrateTo = Math.max(level, this.gamification.celebrateTo);
    try {
      if (this.token) await apiPost('/api/gamification/celebration/ack', { level }, this.token);
    } catch {
      /* non-fatal */
    }
  },
```

- [ ] **Step 4: Cut the three chat triggers**

In `resources/mobile/mixins/onboardingChat.js`, at :197, :363 and :693, delete `store.queueCelebration(cursor.levelUp);` from each line. Whether `this.pulseWheel()` goes with it: **remove it** — the wheel is not visible while the full-screen Fyn overlay is up, so the pulse is spent unseen and it is a second, competing level-up cue. If `pulseWheel` then has no callers, delete the method and the `pulsing` data key. Update the stale comment at :70 which still describes the shared celebration component.

- [ ] **Step 5: Remove the mount and delete the component**

Remove the `<GamificationCelebration>` mount and import from `resources/mobile/views/Dashboard.vue`, then:

```bash
git rm resources/mobile/components/GamificationCelebration.vue
```

- [ ] **Step 6: Animate the `/m` circle**

Apply the same template, data, methods and watchers as Task 3 Step 5 to `resources/mobile/views/Dashboard.vue:29-45`, with these `/m` differences:

- The circle sits inside a `<button class="md-level md-level--button">`; keep the button and put the burst span inside `md-level__pie` exactly as on web.
- The Fyn predicate is local state, not a store getter: `fynOpen: this.fynOpen` (`:455`).
- The watcher is `fynOpen(open) { if (!open) this.playBankedLevels(); }` rather than a store path.
- Import from `../navigation/levelCelebration.js`.
- `/m` is a route-based SPA: also call `playBankedLevels()` when the dashboard route becomes active.

- [ ] **Step 7: Style the burst on `/m`**

The `/m` dashboard styles live in **`resources/mobile/views/dashboard.css`**, not `style.css`. Append the same rules as Task 3 Step 6 there, with two differences:

- **No `.gamified-dash` prefix** — `/m`'s selectors are unprefixed (see `dashboard.css:173-188`).
- Again **no `stroke-dashoffset` transition**: `.md-level__pie-arc` at :178-188 already carries `transition: stroke-dashoffset 0.45s cubic-bezier(0.4, 0, 0.2, 1)`, identical to web.

Confirm `.md-level__pie` is `position: relative` in that file before relying on it for the burst; if it is not, add it.

- [ ] **Step 8: Run the suites**

```bash
npx vitest run
```

Expected: PASS. `resources/mobile/views/__tests__/Dashboard.spec.js` stubs `GamificationCelebration` at **:47, :201 and :227** — remove all three stubs, and add a case asserting the circle shows the climbed number after a banked range.

- [ ] **Step 9: Browser-verify on `/m`**

Rebuild first — `/m` serves a **built** bundle with no HMR, and a stale bundle has cost a full debugging session before:

```bash
npm run build:mobile
```

Then follow the `verify-m` skill. Establish which account you are on from `GET /api/auth/user` with the token, never from `localStorage`. Repeat all four checks from Task 3 Step 8, with the `/m`-specific one: open the full-screen Fyn overlay, bank a level while it is open, close the overlay, and assert the climb runs **on collapse** — this is the case CSJ specifically described.

- [ ] **Step 10: Commit**

```bash
git add resources/mobile
git commit -m "feat(m): the level climbs in the hero circle on /m too

Same rule as web, same test vectors, its own copy because the bundles cannot
import each other. The three queueCelebration calls in the onboarding chat
mixin are gone, so a level-up can no longer interrupt Fyn on /m, and
pulseWheel goes with them - the wheel is not visible behind the full-screen
overlay, so the pulse was spent unseen."
```

---

### Task 5: Native iOS — the same behaviour in SwiftUI

**Files:**
- Create: `ios-native/Fynla/Features/Gamification/LevelCelebrationSequence.swift`
- Create: `ios-native/FynlaTests/LevelCelebrationSequenceTests.swift`
- Modify: `ios-native/Fynla/Features/Gamification/LevelProgressView.swift:7-45`
- Modify: `ios-native/Fynla/Features/Achievements/AchievementsModel.swift:13-15, 47, 164-182`
- Modify: `ios-native/Fynla/Features/Achievements/AchievementsModels.swift:308-311`
- Modify: `ios-native/Fynla/App/AppRootView.swift:438-451, 711`
- Modify: `ios-native/Fynla/Testing/AchievementsUITestSupport.swift:129-136`
- Delete: `ios-native/Fynla/Core/Components/GamificationCelebrationView.swift`
- Test: `ios-native/FynlaTests/AchievementsModelsTests.swift:113` (modify)

**Interfaces:**
- Consumes: Task 1's contract. Mirrors Task 2's module behaviour exactly.
- Produces: `LevelCelebrationSequence.levelsOwed(from:to:) -> [Int]`, `LevelCelebrationSequence.dashboardIsBeingViewed(onDashboard:fynOpen:backgrounded:) -> Bool`, `GamificationStatus.celebrateFrom/celebrateTo: Int`.

- [ ] **Step 1: Write the failing test**

Create `ios-native/FynlaTests/LevelCelebrationSequenceTests.swift` with the same vectors as Task 2 — `levelsOwed` for an empty range, a three-level range, a single crossing, a backwards range, and the view predicate for each of its four cases. Use the project's existing `#expect` style (see `AchievementsModelsTests.swift`).

- [ ] **Step 2: Run it and watch it fail**

Follow the `ios-simulator` skill before any `xcodebuild` — do not stack a second simulator on one already booted.

Run: `xcodebuild test -scheme Fynla -destination 'platform=iOS Simulator,name=iPhone 15' -only-testing:FynlaTests/LevelCelebrationSequenceTests`
Expected: FAIL — `LevelCelebrationSequence` does not exist.

- [ ] **Step 3: Write the sequence type**

Create `ios-native/Fynla/Features/Gamification/LevelCelebrationSequence.swift` — a direct translation of `resources/js/utils/levelCelebration.js`, with the same cross-reference docblock naming the web and `/m` copies, `stepMs = 900`, and `levelsOwed` coalescing a missing `from` to 1 (never to `to`).

- [ ] **Step 4: Update the status model**

In `AchievementsModels.swift:308-311`, replace `pendingCelebration: LevelCelebration?` / `case pendingCelebration = "pending_celebration"` with `celebrateFrom: Int` / `celebrateTo: Int` and their snake_case coding keys. Delete the `LevelCelebration` struct if nothing else uses it. Update `AchievementsUITestSupport.swift:129-136` and `AchievementsModelsTests.swift:113` to the new shape.

- [ ] **Step 5: Remove the takeover**

In `AppRootView.swift`, delete the `if let celebration = achievementsModel.pendingCelebration { GamificationCelebrationView(...) }` block (:438-451) and the `dismissCelebration()` call at :711. Keep `.task { await achievementsModel.refreshCelebration() }` but rename it to reflect that it now refreshes the range. Then:

```bash
git rm ios-native/Fynla/Core/Components/GamificationCelebrationView.swift
```

In `AchievementsModel.swift`, replace `pendingCelebration` (:15, :47, :166, :173, :182) with `celebrateFrom` / `celebrateTo`, and `dismissCelebration()` with `acknowledge(level:)` that POSTs `{ "level": level }`.

- [ ] **Step 6: Animate the wheel**

In `LevelProgressView.swift`, drive the number from a `@State displayLevel` rather than `level.level`, add a confetti overlay on the wheel `ZStack` using spring / raspberry / violet only, and run the climb with `LevelCelebrationSequence` when the dashboard appears and when the Fyn sheet is dismissed. Honour `@Environment(\.accessibilityReduceMotion)`: land on the final number, no confetti, no sweep, **still acknowledge**. Do not use `scenePhase == .background` time to run the climb.

- [ ] **Step 7: Run the tests**

Run: `xcodebuild test -scheme Fynla -destination 'platform=iOS Simulator,name=iPhone 15'`
Expected: PASS, apart from the 6 known-red `Local StoreKit configuration` tests — those are the missing App Store Connect products, a real but unrelated signal.

- [ ] **Step 8: Verify on the simulator**

Both schemes point at **fynla.org** (production), so any account you use is a real one. Repeat the four checks from Task 3 Step 8 on the simulator, plus: open Fyn, bank a level, dismiss the sheet, assert the climb runs on dismissal. Turn on Settings → Accessibility → Motion → Reduce Motion for the reduced-motion pass.

- [ ] **Step 9: Commit**

```bash
git add ios-native
git commit -m "feat(ios): the level climbs in the wheel instead of taking over the app

The root-level takeover is gone, so a level-up can no longer cover a Fyn
conversation on native. Same sequence rule as web and /m, same test vectors,
translated to Swift. Honours Reduce Motion, which still acknowledges."
```

---

### Task 6: Ship it

**Files:**
- Modify: `handover/September/16/sdd-account-forms/progress.md` (ruling 54)

- [ ] **Step 1: Confirm nothing references the old contract**

```bash
grep -rn "pending_celebration\|pendingCelebration\|GamificationCelebration\|queueCelebration" app resources ios-native tests --include="*.php" --include="*.js" --include="*.vue" --include="*.swift" | grep -v node_modules
```

Expected: no matches except the migration's `down()`.

- [ ] **Step 2: Run everything**

```bash
./vendor/bin/pest tests/Feature/Gamification tests/Unit/Services/Gamification
npx vitest run
./vendor/bin/pint app database tests
```

- [ ] **Step 3: Record the ruling**

Append ruling 54 to the ledger: what changed, why the takeover went, that the named ladder is deliberately no longer surfaced at level-up, and that the migration backfill is what stops existing users getting a climb.

- [ ] **Step 4: Open the PR to `dev`**

Include: the four verification passes per surface, the reduced-motion pass, and an explicit note that **the migration must run on deploy** and backfills every row.

- [ ] **Step 5: Deploy to csjones and browser-verify there before merging**

Per the `release` skill Flow A: build with `./deploy/csjones-fynla/build.sh`, check the feature branch out on csjones, `php artisan migrate --force`, upload **both** bundles, cache clears ending `config:cache`. Then repeat the Task 3 Step 8 and Task 4 Step 9 checks on csjones. Only then merge.

---

## Self-review

**Spec coverage**

| Spec section | Task |
|---|---|
| §1 Data model, migration backfill | Task 1 steps 3, 9 |
| §2 API contract, ack idempotency, SSE frame stays | Task 1 steps 5, 8; Task 3 step 3 |
| §3 Trigger predicate, three surfaces | Task 2 (`dashboardIsBeingViewed`), Task 3 step 5, Task 4 step 6, Task 5 step 6 |
| §4 Animation, pacing, ring sweep, reduced motion | Task 3 steps 5-6, Task 4 steps 6-7, Task 5 step 6 |
| §5 Deletions, all five trigger sites | Task 3 steps 3-4, Task 4 steps 4-5, Task 5 step 5, Task 6 step 1 |
| §6 Testing, live verification | Task 1 step 1, Task 2 step 1, Task 3 step 8, Task 4 step 9, Task 5 step 8 |
| §7 `pulseWheel` decision | Task 4 step 4 — resolved to remove |
| §8 Consequences | Task 6 step 3 |

**Placeholders:** none — every code step carries the code. Task 5 steps 3 and 6 describe a Swift translation rather than printing it, because the source of truth is the JavaScript in Task 2 and the vectors in Task 5 step 1 pin the behaviour; that is deliberate, not a gap.

**Type consistency:** `levelsOwed`, `dashboardIsBeingViewed`, `prefersReducedMotion`, `runLevelSequence`, `STEP_MS` are named identically in Tasks 2, 4 and 5. `celebrate_from`/`celebrate_to` (wire), `celebrateFrom`/`celebrateTo` (all three clients) and `celebrated_level` (column) are used consistently. `ackCelebration(level)` takes a level in every client. The web `data()` key is `displayLevel` and the template reads `shownLevel` — Task 3 step 5 states this explicitly.
