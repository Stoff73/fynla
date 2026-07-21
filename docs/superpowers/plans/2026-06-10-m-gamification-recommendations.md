# /m Gamification + Recommendations Unification — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the `/m` mobile dashboard's recommendations and gamification to real, per-user, KYC-gated data — one unified ≤4 next-actions list feeding both the wheel box and the recs list, a planning-progress percentile, an Achievements panel, a Fyn KYC bubble, and varied preview-persona seeding.

**Architecture:** A new `NextActionsService` produces a single ranked ≤4 list (real recommendations ∪ KYC-unlock prompts). The shared `RecommendationsAggregatorService` is KYC-gated per module (via `PrerequisiteGateService`) and gains the missing investment + goals modules. A new `PlanningProgressService` computes a composite completeness score and percentile. A new `MobileAchievementsController` powers a new `Achievements.vue`. The Vue dashboard renders the unified list, makes the wheel a button to the panel, shows a Fyn unlock bubble, and lowers the milestone banner. A seeder gives preview personas differing gamification data.

**Tech Stack:** Laravel 10 (PHP 8.2, Pest), Vue 3 (mobile micro-SPA under `resources/mobile/`), MySQL 8.

**Spec:** `docs/superpowers/specs/2026-06-10-m-gamification-recommendations-design.md`

**Branch:** `m-gamification-recommendations` (already created off `dev`; the spec is committed there).

---

## Conventions for every task

- PHP files: `declare(strict_types=1);`, PSR-12, type hints, constructor injection with `private readonly`.
- Run a single Pest file with an isolated test DB — NEVER `--env=testing` (no `.env.testing`; it wipes the dev DB). Use the project's standard `./vendor/bin/pest <path>` which uses `RefreshDatabase` on the configured test connection.
- User-facing strings: British spelling, no acronyms except ISA (Rule #9), no emoji/icons in dashboard/Fyn text (Rule #15). Tax/allowance figures via `TaxConfigService` only (Rule #2).
- Commit after each task with the message shown.

---

## File Structure

**New backend**
- `app/Services/Mobile/NextActionsService.php` — builds the unified ≤4 list.
- `app/Services/Mobile/PlanningProgressService.php` — composite score + percentile.
- `app/Http/Controllers/Api/V1/Mobile/MobileAchievementsController.php` — achievements + milestones API.
- `database/seeders/PreviewGamificationSeeder.php` — varied persona gamification/tracking.

**New frontend**
- `resources/mobile/views/Achievements.vue` — two-tab panel.

**New tests**
- `tests/Unit/Services/Mobile/NextActionsServiceTest.php`
- `tests/Unit/Services/Mobile/PlanningProgressServiceTest.php`
- `tests/Unit/Services/Coordination/RecommendationsAggregatorGatingTest.php`
- `tests/Feature/Mobile/MobileAchievementsTest.php`
- `tests/Feature/Mobile/MobileDashboardNextActionsTest.php`

**Modified backend**
- `app/Services/Coordination/RecommendationsAggregatorService.php` — KYC-gate every module; replace unused `PortfolioAnalyzer` with `InvestmentAgent`; add `GoalsAgent`.
- `app/Services/Mobile/MobileLevelService.php` — percentile delegates to `PlanningProgressService`; "X of Y" derives from the passed next-actions.
- `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php` — build next-actions once; emit `next_actions`; new percentile.
- `routes/api_v1.php` — add `GET /mobile/achievements`.
- `config/gamification.php` — add `unlock_action_weight`.
- `database/seeders/DatabaseSeeder.php` — call `PreviewGamificationSeeder`.

**Modified frontend**
- `resources/mobile/views/Dashboard.vue` — unified list, wheel→button, Fyn unlock bubble.
- `resources/mobile/views/dashboard.css` — milestone banner position.
- `resources/mobile/router.js` — `/achievements` route.

---

## Data contracts (locked — keep identical across tasks)

**Next-action item** (returned by `NextActionsService::build`, carried as `next_actions` in the dashboard payload):

```php
[
  'id'      => string,                 // rec id, or "unlock:{module}"
  'type'    => 'recommendation'|'unlock',
  'module'  => string,                 // protection|savings|investment|retirement|estate|goals
  'title'   => string,                 // plain text
  'meta'    => string,                 // "You could save £1,240" | "2 quick questions to unlock"
  'value'   => float,                  // sort weight (desc)
  'done'    => bool,                   // recommendation_tracking completed; always false for unlock
  'action'  => [
     'kind'    => 'rec_chat'|'fyn_capture'|'deeplink',
     'payload' => string,              // rec title | module key | route
  ],
]
```

**Level payload** (`MobileLevelService::levelFor`): unchanged keys, but `actions_completed`/`actions_total` now equal the done/total counts of the ≤4 next-actions.

**Achievements payload** (`GET /api/v1/mobile/achievements`):

```php
[
  'achievements' => [ ['key'=>string,'title'=>string,'description'=>string,'earned'=>bool,'earned_at'=>?string] , ... ],
  'next'         => [ /* the ≤4 next-action items above */ ],
  'milestones'   => [ ['key'=>string,'title'=>string,'achieved'=>bool,'achieved_at'=>?string] , ... ],
]
```

---

# Phase A — KYC-gate the recommendation engine + add investment & goals

### Task A1: Gating + investment/goals coverage in the aggregator

**Files:**
- Modify: `app/Services/Coordination/RecommendationsAggregatorService.php`
- Test: `tests/Unit/Services/Coordination/RecommendationsAggregatorGatingTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;

it('does not emit estate recommendations when estate KYC is unmet', function () {
    // A user with no estate data at all (fresh).
    $user = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '2001-01-01',
    ]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    $estate = array_filter($recs, fn ($r) => ($r['module'] ?? '') === 'estate');
    expect($estate)->toBeEmpty();
});

it('tags each recommendation with the gate-satisfied module only', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    foreach ($recs as $r) {
        expect($r)->toHaveKeys(['module', 'recommendation_text', 'priority_score']);
    }
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/RecommendationsAggregatorGatingTest.php`
Expected: FAIL — estate recs currently emitted unconditionally.

- [ ] **Step 3: Edit the aggregator — constructor**

Replace the constructor and imports. Swap the unused `PortfolioAnalyzer` for `InvestmentAgent`, add `GoalsAgent` and `PrerequisiteGateService`:

```php
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\User;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\PrerequisiteGateService;
use Illuminate\Support\Facades\Log;

class RecommendationsAggregatorService
{
    public function __construct(
        private readonly ProtectionAgent $protectionEngine,
        private readonly SavingsAgent $savingsCalculator,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly ComprehensiveEstatePlanService $estatePlanService,
        private readonly GoalsAgent $goalsAgent,
        private readonly RecommendationPersonaliser $personaliser,
        private readonly PrerequisiteGateService $gate,
    ) {}
```

- [ ] **Step 4: Edit `aggregateRecommendations` — wrap each module in its gate**

Add a private helper and gate each existing block. Insert this helper after `aggregateRecommendations`:

```php
    /**
     * True when the named module's KYC prerequisites are satisfied for the user.
     * Modules map 1:1 to PrerequisiteGateService actions.
     */
    private function moduleGateOpen(string $module, User $user): bool
    {
        return $this->gate->enforce($module, $user)['can_proceed'] === true;
    }
```

Then guard each block. Protection:

```php
        // Protection module
        if ($this->moduleGateOpen('protection', $user)) {
            try {
                $protectionAnalysis = $this->protectionEngine->analyze($userId);
                // ... existing protection body unchanged ...
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($protectionRecs, 'protection'));
            } catch (\Exception $e) {
                Log::warning("Failed to get protection recommendations for user {$userId}: ".$e->getMessage());
            }
        }
```

Apply the identical `if ($this->moduleGateOpen('savings', $user)) { ... }`, `if ($this->moduleGateOpen('retirement', $user)) { ... }`, `if ($this->moduleGateOpen('estate', $user)) { ... }` wrappers around the existing savings, retirement, and estate blocks respectively (leave their inner bodies unchanged).

- [ ] **Step 5: Add the investment block (was never present)**

Insert after the retirement block, before estate:

```php
        // Investment module
        if ($this->moduleGateOpen('investment', $user)) {
            try {
                $investmentAnalysis = $this->investmentAgent->analyze($userId);
                $generated = $this->investmentAgent->generateRecommendations($investmentAnalysis['data'] ?? $investmentAnalysis);
                $investmentRecs = array_map(static function (array $r): array {
                    return [
                        'recommendation_text' => $r['title'] ?? $r['recommendation'] ?? $r['action'] ?? '',
                        'priority_score' => isset($r['priority']) ? max(40, 90 - ((int) $r['priority'] * 5)) : 55,
                        'category' => $r['category'] ?? 'investment',
                    ];
                }, $generated['recommendations'] ?? []);
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($investmentRecs, 'investment'));
            } catch (\Exception $e) {
                Log::warning("Failed to get investment recommendations for user {$userId}: ".$e->getMessage());
            }
        }
```

- [ ] **Step 6: Add the goals block**

Insert after the estate block, before the personaliser call:

```php
        // Goals module
        if ($this->moduleGateOpen('goals', $user)) {
            try {
                $goalsAnalysis = $this->goalsAgent->analyze($userId);
                $generated = $this->goalsAgent->generateRecommendations($goalsAnalysis['data'] ?? $goalsAnalysis);
                $goalsRecs = array_map(static function (array $r): array {
                    return [
                        'recommendation_text' => $r['title'] ?? $r['action'] ?? $r['description'] ?? '',
                        'priority_score' => isset($r['priority']) ? max(40, 90 - ((int) $r['priority'] * 5)) : 50,
                        'category' => $r['category'] ?? 'goals',
                    ];
                }, $generated['recommendations'] ?? []);
                $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($goalsRecs, 'goals'));
            } catch (\Exception $e) {
                Log::warning("Failed to get goals recommendations for user {$userId}: ".$e->getMessage());
            }
        }
```

- [ ] **Step 7: Add 'goals' + 'investment' to the `determineCategory` match and `getSummary` by_module**

In `determineCategory`, the `match ($module)` already has `investment` mapped via default earlier? Ensure these arms exist:

```php
        return match ($module) {
            'protection' => 'risk_mitigation',
            'savings' => 'liquidity_management',
            'investment' => 'growth_optimization',
            'retirement' => 'retirement_planning',
            'estate' => 'tax_optimization',
            'goals' => 'goal_planning',
            default => 'general',
        };
```

In `getSummary`'s `by_module` array add `'goals' => 0,`.

- [ ] **Step 8: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/RecommendationsAggregatorGatingTest.php`
Expected: PASS.

- [ ] **Step 9: Run the existing recommendation tests to check no regression**

Run: `./vendor/bin/pest tests/ --filter=Recommendation`
Expected: PASS (or pre-existing failures unrelated to this change — note any).

- [ ] **Step 10: Commit**

```bash
git add app/Services/Coordination/RecommendationsAggregatorService.php tests/Unit/Services/Coordination/RecommendationsAggregatorGatingTest.php
git commit -m "feat(recs): KYC-gate the aggregator and add investment + goals coverage

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase B — Unified Next-Actions service

### Task B1: `NextActionsService`

**Files:**
- Create: `app/Services/Mobile/NextActionsService.php`
- Modify: `config/gamification.php`
- Test: `tests/Unit/Services/Mobile/NextActionsServiceTest.php`

- [ ] **Step 1: Add the config weight**

In `config/gamification.php`, inside the top-level array (after `'points' => [...]`), add:

```php
    // Sort weight given to a KYC "unlock" action when interleaved with real
    // recommendations in the mobile next-actions list (tunable).
    'unlock_action_weight' => 65,
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mobile\NextActionsService;

it('caps the unified list at four items', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    expect(count($items))->toBeLessThanOrEqual(4);
});

it('emits an unlock item for a gated module carrying a deeplink action', function () {
    // Fresh user: estate (and most modules) gated.
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    $unlock = collect($items)->firstWhere('type', 'unlock');
    expect($unlock)->not->toBeNull()
        ->and($unlock['action']['kind'])->toBeIn(['fyn_capture', 'deeplink'])
        ->and($unlock['done'])->toBeFalse();
});

it('sorts by value descending', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);
    $values = array_column($items, 'value');
    $sorted = $values;
    rsort($sorted);

    expect($values)->toEqual($sorted);
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Mobile/NextActionsServiceTest.php`
Expected: FAIL — class does not exist.

- [ ] **Step 4: Create the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\PrerequisiteGateService;

/**
 * Builds the single ranked next-actions list (max 4) shown on the /m dashboard
 * wheel box AND the recommendations list below — they are the same list. Items
 * are either real recommendations (from the KYC-gated aggregator) or KYC
 * "unlock" prompts for high-value gated modules.
 */
class NextActionsService
{
    private const MAX_ITEMS = 4;

    /** Modules that can produce an unlock prompt, in surfacing priority order. */
    private const UNLOCK_MODULES = ['retirement', 'protection', 'savings', 'investment', 'estate', 'goals'];

    public function __construct(
        private readonly RecommendationsAggregatorService $recommendations,
        private readonly PrerequisiteGateService $gate,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function build(int $userId): array
    {
        $user = User::findOrFail($userId);

        $items = array_merge(
            $this->recommendationItems($userId),
            $this->unlockItems($user),
        );

        usort($items, static function (array $a, array $b): int {
            return [$b['value'], $a['module']] <=> [$a['value'], $b['module']];
        });

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function recommendationItems(int $userId): array
    {
        $all = $this->recommendations->aggregateRecommendations($userId);

        $completedIds = RecommendationTracking::where('user_id', $userId)
            ->where('status', 'completed')
            ->pluck('recommendation_id')
            ->all();

        return array_map(function (array $rec) use ($completedIds): array {
            $benefit = is_numeric($rec['potential_benefit'] ?? null) ? (float) $rec['potential_benefit'] : null;
            $id = (string) ($rec['recommendation_id'] ?? uniqid('rec_'));

            return [
                'id' => $id,
                'type' => 'recommendation',
                'module' => (string) ($rec['module'] ?? 'general'),
                'title' => (string) ($rec['recommendation_text'] ?? ''),
                'meta' => $benefit !== null
                    ? 'You could save £'.number_format($benefit)
                    : ucfirst(str_replace('_', ' ', (string) ($rec['category'] ?? 'Recommended'))),
                'value' => $benefit ?? (float) ($rec['priority_score'] ?? 50),
                'done' => in_array($id, $completedIds, true),
                'action' => ['kind' => 'rec_chat', 'payload' => (string) ($rec['recommendation_text'] ?? '')],
            ];
        }, $all);
    }

    /**
     * One unlock item per gated module (highest-priority gated module only is
     * needed once ranking caps at 4, but we emit all and let the sort decide).
     *
     * @return array<int,array<string,mixed>>
     */
    private function unlockItems(User $user): array
    {
        $weight = (float) config('gamification.unlock_action_weight', 65);
        $items = [];

        foreach (self::UNLOCK_MODULES as $module) {
            $gate = $this->gate->enforce($module, $user);
            if ($gate['can_proceed'] === true) {
                continue;
            }

            $action = $gate['required_actions'][0] ?? ['label' => 'Add your details', 'route' => '/dashboard'];

            $items[] = [
                'id' => 'unlock:'.$module,
                'type' => 'unlock',
                'module' => $module,
                'title' => 'Unlock '.$this->moduleLabel($module).' advice',
                'meta' => $action['label'] ?? 'A few quick questions',
                'value' => $weight,
                'done' => false,
                'action' => ['kind' => 'fyn_capture', 'payload' => $module],
            ];
        }

        return $items;
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'protection' => 'protection',
            'savings' => 'savings',
            'investment' => 'investment',
            'retirement' => 'retirement',
            'estate' => 'estate planning',
            'goals' => 'goals',
            default => $module,
        };
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Mobile/NextActionsServiceTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Mobile/NextActionsService.php config/gamification.php tests/Unit/Services/Mobile/NextActionsServiceTest.php
git commit -m "feat(mobile): NextActionsService — unified <=4 recs + KYC-unlock list

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase C — Planning-progress percentile

### Task C1: `PlanningProgressService`

**Files:**
- Create: `app/Services/Mobile/PlanningProgressService.php`
- Test: `tests/Unit/Services/Mobile/PlanningProgressServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mobile\PlanningProgressService;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('scores between 0 and 100', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $score = app(PlanningProgressService::class)->scoreFor($user);

    expect($score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('bounds the percentile to 1..99', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $pct = app(PlanningProgressService::class)->percentileFor($user);

    expect($pct)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(99);
});

it('ranks a more-complete user above a blank one in the same cohort', function () {
    $blank = User::factory()->create(['is_preview_user' => false]);
    $rich = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '1980-01-01',
        'marital_status' => 'married',
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'monthly_expenditure' => 2000,
    ]);

    $svc = app(PlanningProgressService::class);

    expect($svc->scoreFor($rich))->toBeGreaterThan($svc->scoreFor($blank));
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Mobile/PlanningProgressServiceTest.php`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Create the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\PrerequisiteGateService;
use Illuminate\Support\Facades\Cache;

/**
 * Composite "planning progress" score (0..100) and a peer percentile.
 *
 * The percentile cohort is the viewer's own preview class (preview personas
 * ranked among preview personas; real users among real users) so preview
 * testing is meaningful AND prod percentiles never include seeded personas.
 * (CSJ-approved Rule #12 gamification metric — NOT a financial-quality score.)
 */
class PlanningProgressService
{
    private const DISTRIBUTION_TTL = 3600; // 1 hour — slow-moving

    /** Composite weights (sum 100). */
    private const W_MODULES = 40;
    private const W_RECS = 25;
    private const W_MILESTONES = 20;
    private const W_UNIVERSAL = 15;

    private const MODULES = ['protection', 'savings', 'retirement', 'investment', 'estate', 'goals'];

    public function __construct(private readonly PrerequisiteGateService $gate) {}

    public function scoreFor(User $user): int
    {
        // Module readiness (gates passing) — main driver.
        $openModules = 0;
        foreach (self::MODULES as $module) {
            if ($this->gate->enforce($module, $user)['can_proceed'] === true) {
                $openModules++;
            }
        }
        $moduleScore = ($openModules / count(self::MODULES)) * self::W_MODULES;

        // Recommendations actioned (cap 10).
        $completed = RecommendationTracking::where('user_id', $user->id)
            ->where('status', 'completed')->count();
        $recScore = (min($completed, 10) / 10) * self::W_RECS;

        // Milestones reached (cap 8).
        $milestones = UserMilestone::where('user_id', $user->id)->count();
        $milestoneScore = (min($milestones, 8) / 8) * self::W_MILESTONES;

        // Universal KYC fields (5).
        $universal = 0;
        $universal += $user->date_of_birth ? 1 : 0;
        $universal += $user->marital_status ? 1 : 0;
        $universal += $user->employment_status ? 1 : 0;
        $universal += $this->totalIncome($user) > 0 ? 1 : 0;
        $universal += ((float) ($user->monthly_expenditure ?? 0)) > 0 ? 1 : 0;
        $universalScore = ($universal / 5) * self::W_UNIVERSAL;

        return (int) round($moduleScore + $recScore + $milestoneScore + $universalScore);
    }

    public function percentileFor(User $user): int
    {
        $score = $this->scoreFor($user);
        $distribution = $this->distribution((bool) $user->is_preview_user);

        $total = count($distribution);
        if ($total <= 1) {
            return 50;
        }

        $below = count(array_filter($distribution, static fn (int $s) => $s < $score));
        $pct = (int) round(($below / $total) * 100);

        return max(1, min(99, $pct));
    }

    /**
     * Scores of every user in the given preview class. Cached 1h.
     *
     * @return array<int,int>
     */
    private function distribution(bool $preview): array
    {
        $key = 'planning_progress_dist:'.($preview ? 'preview' : 'real');

        return Cache::remember($key, self::DISTRIBUTION_TTL, function () use ($preview) {
            return User::query()
                ->where('is_preview_user', $preview)
                ->get()
                ->map(fn (User $u) => $this->scoreFor($u))
                ->all();
        });
    }

    public function clearCache(): void
    {
        Cache::forget('planning_progress_dist:preview');
        Cache::forget('planning_progress_dist:real');
    }

    private function totalIncome(User $user): float
    {
        return (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;
    }
}
```

NOTE: confirm the milestone model class name before running — `grep -rn "class UserMilestone" app/Models`. If it is `UserMilestones` (plural) or namespaced, fix the `use` and references accordingly.

- [ ] **Step 4: Verify the milestone model name**

Run: `grep -rn "class UserMilestone" app/Models`
Expected: prints the real class name. Adjust the `use App\Models\UserMilestone;` import + the two `UserMilestone::` references if it differs.

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Mobile/PlanningProgressServiceTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Mobile/PlanningProgressService.php tests/Unit/Services/Mobile/PlanningProgressServiceTest.php
git commit -m "feat(mobile): PlanningProgressService — composite score + cohort percentile

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase D — Wire the dashboard endpoint

### Task D1: `MobileLevelService` — actions from next-actions, percentile delegated

**Files:**
- Modify: `app/Services/Mobile/MobileLevelService.php`

- [ ] **Step 1: Change `levelFor` to accept the next-actions list**

Replace the `levelFor` signature + body so the "X of Y" counts come from the passed list (never "0 of 0" when actions exist):

```php
    /**
     * @param  array<int,array<string,mixed>>  $nextActions  the unified <=4 list
     */
    public function levelFor(int $userId, array $nextActions = []): array
    {
        $points = (int) (UserGamification::where('user_id', $userId)->value('total_points') ?? 0);
        $progress = $this->levels->progress($points);

        $total = count($nextActions);
        $completed = count(array_filter($nextActions, static fn ($a) => ($a['done'] ?? false) === true));

        return [
            'level' => $progress['level'],
            'level_name' => $progress['level_name'],
            'next_level_name' => $progress['next_level_name'],
            'progress_percent' => $progress['progress_percent'],
            'actions_completed' => $completed,
            'actions_total' => $total,
            'actions_in_level' => $completed,
            'actions_for_next' => $total,
        ];
    }
```

- [ ] **Step 2: Delete the old `percentile` + `levelDistribution` methods**

Remove `percentile()`, `levelDistribution()`, and the `PERCENTILE_CACHE_TTL` const from `MobileLevelService` (percentile now lives in `PlanningProgressService`). Keep `clearCache()` but make it a no-op or remove its body referencing the deleted cache key:

```php
    public function clearCache(int $userId): void
    {
        // Distribution caching moved to PlanningProgressService.
    }
```

Remove the now-unused `RecommendationTracking`, `Cache`, and `DB` imports if nothing else uses them.

- [ ] **Step 3: Commit**

```bash
git add app/Services/Mobile/MobileLevelService.php
git commit -m "refactor(mobile): level actions from next-actions; percentile moved out

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

### Task D2: Controller emits `next_actions` + new percentile

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php`
- Test: `tests/Feature/Mobile/MobileDashboardNextActionsTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns a unified next_actions list of at most four items', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/dashboard');

    $res->assertOk();
    $actions = $res->json('data.next_actions');
    expect($actions)->toBeArray()
        ->and(count($actions))->toBeLessThanOrEqual(4);
    $res->assertJsonPath('data.level.actions_total', count($actions));
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileDashboardNextActionsTest.php`
Expected: FAIL — `data.next_actions` absent.

- [ ] **Step 3: Edit the controller — constructor**

Replace `RecommendationsAggregatorService` injection with the two new services and keep what's still used:

```php
use App\Services\Mobile\MilestoneDetectionService;
use App\Services\Mobile\MobileDashboardAggregator;
use App\Services\Mobile\MobileLevelService;
use App\Services\Mobile\NextActionsService;
use App\Services\Mobile\PlanningProgressService;
// ...

    public function __construct(
        private readonly MobileDashboardAggregator $aggregator,
        private readonly MobileLevelService $levelService,
        private readonly NextActionsService $nextActions,
        private readonly PlanningProgressService $planningProgress,
        private readonly MilestoneDetectionService $milestones,
    ) {}
```

- [ ] **Step 4: Edit `index()` — build actions once**

Replace the level/percentile/recommendations block:

```php
            $data = $this->aggregator->getAggregatedDashboard($userId);

            // Unified next-actions: the SAME <=4 list drives the wheel box and
            // the recommendations list below (spec decision B).
            $actions = $this->nextActions->build($userId);
            $data['next_actions'] = $actions;

            // Level ring + "X of Y actions" derived from that list.
            $data['level'] = $this->levelService->levelFor($userId, $actions);

            // Planning-progress percentile (cohort = viewer's preview class).
            $data['percentile'] = $this->planningProgress->percentileFor($request->user());

            $data['new_milestones'] = $this->detectMilestones($request->user(), $data);
```

- [ ] **Step 5: Delete the now-dead `mobileRecommendations()` method**

Remove the entire `private function mobileRecommendations(int $userId): array { ... }` (lines ~97-143) and the `RecommendationsAggregatorService`/`Goal` imports if unused elsewhere in the file. (`Goal` is still used by `detectMilestones` — keep it.)

- [ ] **Step 6: Run the feature test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileDashboardNextActionsTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php tests/Feature/Mobile/MobileDashboardNextActionsTest.php
git commit -m "feat(mobile): dashboard emits unified next_actions + planning percentile

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase E — Achievements API

### Task E1: `MobileAchievementsController` + route

**Files:**
- Create: `app/Http/Controllers/Api/V1/Mobile/MobileAchievementsController.php`
- Modify: `routes/api_v1.php`
- Test: `tests/Feature/Mobile/MobileAchievementsTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns achievements, next actions, and milestones', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/achievements');

    $res->assertOk()
        ->assertJsonStructure(['success', 'data' => ['achievements', 'next', 'milestones']]);
    expect(count($res->json('data.next')))->toBeLessThanOrEqual(4);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileAchievementsTest.php`
Expected: FAIL — route 404.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserMilestone;
use App\Services\Gamification\LevelService;
use App\Services\Mobile\NextActionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAchievementsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly NextActionsService $nextActions,
        private readonly LevelService $levels,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $this->achievements($user),
                    'next' => $this->nextActions->build($user->id),
                    'milestones' => $this->milestones($user),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Fetching achievements');
        }
    }

    /**
     * Earned badges derived from the point_awards ledger + current level.
     *
     * @return array<int,array<string,mixed>>
     */
    private function achievements(User $user): array
    {
        $g = UserGamification::where('user_id', $user->id)->first();
        $level = $g?->level ?? 1;
        $awards = PointAward::where('user_id', $user->id)->get();

        $out = [];

        // Level reached.
        $out[] = [
            'key' => 'level',
            'title' => 'Reached '.$this->levels->levelName($level),
            'description' => 'Your current planning level.',
            'earned' => $level > 1,
            'earned_at' => null,
        ];

        // First-data-in-category badges.
        foreach (['protection', 'savings', 'investment', 'retirement', 'estate', 'goals'] as $cat) {
            $award = $awards->first(fn ($a) => $a->dedup_key === "data:{$cat}:first");
            $out[] = [
                'key' => 'data_'.$cat,
                'title' => 'Added '.$cat.' details',
                'description' => 'You started building your '.$cat.' picture.',
                'earned' => $award !== null,
                'earned_at' => $award?->created_at?->toIso8601String(),
            ];
        }

        // Recommendations completed.
        $recCount = $awards->where('source_type', 'recommendation')->count();
        $out[] = [
            'key' => 'recs_actioned',
            'title' => 'Actioned '.$recCount.' recommendation'.($recCount === 1 ? '' : 's'),
            'description' => 'Keep acting on recommendations to progress.',
            'earned' => $recCount > 0,
            'earned_at' => null,
        ];

        // Login streak.
        $streak = $g?->login_streak_days ?? 0;
        $out[] = [
            'key' => 'streak',
            'title' => $streak.'-day check-in streak',
            'description' => 'Log in daily to keep your streak.',
            'earned' => $streak >= 3,
            'earned_at' => null,
        ];

        return $out;
    }

    /**
     * Financial milestones (achieved + the catalogue), from user_milestones.
     *
     * @return array<int,array<string,mixed>>
     */
    private function milestones(User $user): array
    {
        return UserMilestone::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserMilestone $m) => [
                'key' => (string) ($m->milestone_key ?? $m->key ?? $m->id),
                'title' => (string) ($m->label ?? $m->title ?? 'Milestone'),
                'achieved' => true,
                'achieved_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
```

NOTE: before running, verify column names on the milestone model with `grep -rn "class UserMilestone" app/Models` then open it — adjust `milestone_key`/`label` to the real columns.

- [ ] **Step 4: Add the route**

In `routes/api_v1.php`, inside the `auth:sanctum` group, after the dashboard route (around line 50), add:

```php
use App\Http\Controllers\Api\V1\Mobile\MobileAchievementsController;
// ...
    Route::get('/mobile/achievements', [MobileAchievementsController::class, 'index'])
        ->middleware(['etag', 'throttle:mobile-dashboard'])
        ->name('api.v1.mobile.achievements');
```

(Add the `use` line at the top with the other controller imports.)

- [ ] **Step 5: Run the feature test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileAchievementsTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/Mobile/MobileAchievementsController.php routes/api_v1.php tests/Feature/Mobile/MobileAchievementsTest.php
git commit -m "feat(mobile): achievements + milestones API endpoint

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase F — Preview-persona seeding

### Task F1: `PreviewGamificationSeeder`

**Files:**
- Create: `database/seeders/PreviewGamificationSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create the seeder**

Gives each preview persona differing points/level + a mix of completed/pending tracking rows so the wheel, percentile, and achievements vary persona-to-persona. Writes rows directly (PointsService no-ops for preview users by design).

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PointAward;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\LevelService;
use Illuminate\Database\Seeder;

class PreviewGamificationSeeder extends Seeder
{
    /** Per-persona target points keyed by email fragment (varied by life stage). */
    private const TARGETS = [
        'young_family' => 180,
        'peak_earners' => 620,
        'entrepreneur' => 360,
        'young_saver' => 70,
        'retired_couple' => 980,
        'student' => 30,
    ];

    public function run(): void
    {
        $levels = app(LevelService::class);

        $personas = User::where('is_preview_user', true)->get();

        foreach ($personas as $user) {
            $points = $this->pointsFor($user->email);

            $g = UserGamification::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'total_points' => $points,
                    'level' => $levels->levelForPoints($points),
                    'login_streak_days' => $points > 200 ? 5 : 1,
                ],
            );

            // A representative onboarding-answer award so the ledger isn't empty.
            PointAward::updateOrCreate(
                ['user_id' => $user->id, 'dedup_key' => 'seed:onboarding'],
                ['source_type' => 'onboarding_answer', 'points' => 10, 'meta' => ['seeded' => true]],
            );

            // Recommendation tracking: a mix so "X of Y" is non-trivial.
            $completed = max(0, intdiv($points, 120));
            for ($i = 0; $i < 3; $i++) {
                RecommendationTracking::updateOrCreate(
                    ['user_id' => $user->id, 'recommendation_id' => "seed_{$user->id}_{$i}"],
                    [
                        'module' => ['savings', 'retirement', 'protection'][$i],
                        'status' => $i < $completed ? 'completed' : 'pending',
                        'priority_score' => 60,
                    ],
                );
            }
        }
    }

    private function pointsFor(string $email): int
    {
        foreach (self::TARGETS as $fragment => $points) {
            if (str_contains($email, $fragment)) {
                return $points;
            }
        }

        return 40;
    }
}
```

NOTE: verify `recommendation_tracking` required columns with `grep -rn "Schema::create('recommendation_tracking'" database/migrations` (open the migration) — add any NOT NULL columns the `updateOrCreate` second array is missing.

- [ ] **Step 2: Verify the tracking table columns**

Run: `grep -rln "recommendation_tracking" database/migrations`
Then open the migration and confirm the columns used (`module`, `status`, `priority_score`, `recommendation_id`, `user_id`) exist and there are no other NOT NULL columns. Adjust the seeder if so.

- [ ] **Step 3: Register the seeder**

In `database/seeders/DatabaseSeeder.php`, find where `PreviewUserSeeder::class` is called and add immediately AFTER it:

```php
            PreviewGamificationSeeder::class,
```

(If the seeders are listed in a `$this->call([...])` array, add `PreviewGamificationSeeder::class,` right after `PreviewUserSeeder::class,`.)

- [ ] **Step 4: Run the seeder and verify variation**

Run:
```bash
php artisan db:seed --class=PreviewGamificationSeeder --force
php artisan tinker --execute="foreach (\App\Models\UserGamification::whereIn('user_id', \App\Models\User::where('is_preview_user',true)->pluck('id'))->get() as \$g) { echo \$g->user_id.' pts='.\$g->total_points.' lvl='.\$g->level.PHP_EOL; }"
```
Expected: preview users now have DIFFERING points/levels (not all 0/1).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/PreviewGamificationSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(seed): varied gamification + tracking for preview personas

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase G — Dashboard frontend: unified list, wheel button, Fyn bubble, banner

### Task G1: Render the unified next-actions list + make the wheel a button

**Files:**
- Modify: `resources/mobile/views/Dashboard.vue`

- [ ] **Step 1: Consume `next_actions` in the dashboard fetch**

In the `<script>` data, replace the recommendations-bucket state with a flat list. Find where the dashboard response is mapped (the `apiGet('/api/v1/mobile/dashboard', ...)` handler around line 607) and set:

```js
        this.nextActions = Array.isArray(res.data?.data?.next_actions) ? res.data.data.next_actions : [];
        this.percentile = res.data?.data?.percentile ?? this.percentile;
        const lvl = res.data?.data?.level || {};
        this.actionsCompleted = lvl.actions_completed ?? 0;
        this.actionsTotal = lvl.actions_total ?? 0;
```

Add `nextActions: []` to the component `data()` and remove the now-unused `recommendations`/bucket/`orderedRecs` state and the accordion category code that referenced them.

- [ ] **Step 2: Replace the recommendations markup with the unified list**

Replace the `<section class="md-recs ...">` block (template lines ~89-145) with a single list bound to `nextActions`:

```html
        <ul class="md-recs__list" v-if="nextActions.length">
          <li v-for="item in nextActions" :key="item.id" class="md-rec" :class="{ 'is-done': item.done, 'is-unlock': item.type === 'unlock' }">
            <button v-if="item.type === 'recommendation'" class="md-rec__check-btn" @click="toggleRec(item)" :aria-pressed="item.done ? 'true' : 'false'">
              <span class="md-rec__check"></span>
            </button>
            <div class="md-rec__body">
              <p class="md-rec__title">{{ item.title }}</p>
              <p class="md-rec__meta">{{ item.meta }}</p>
            </div>
            <button class="md-rec__action" @click="onActionTap(item)">
              {{ item.type === 'unlock' ? 'Add' : 'Ask Fyn' }}
            </button>
          </li>
        </ul>
        <p v-else class="md-recs__empty">You're all set for now — add more financial details to unlock new actions.</p>
```

- [ ] **Step 3: Make the wheel/level box a button to the achievements panel**

Wrap the level section (template lines ~23-40) so the whole box routes to achievements:

```html
        <button type="button" class="md-level md-level--button" @click="goToAchievements" aria-label="View achievements and milestones">
          <!-- existing md-level__pie + md-level__copy markup unchanged -->
        </button>
```

Add methods:

```js
    goToAchievements() {
      this.$router.push('/achievements');
    },
    onActionTap(item) {
      if (item.type === 'unlock') {
        this.openFynForCapture(item.module);
      } else {
        this.openRecChat({ title: item.title });
      }
    },
    async toggleRec(item) {
      if (item.type !== 'recommendation') return;
      item.done = !item.done;
      try { await apiPost(`/api/recommendations/${item.id}/mark-done`, { done: item.done }, store.token); }
      catch (e) { item.done = !item.done; }
    },
```

(Keep the existing `openRecChat`. `openFynForCapture` is added in Task G2.)

- [ ] **Step 4: Manual check — no console errors, list renders**

Run the dev server if not running (`./dev.sh`), open `http://localhost:8000/m` as a seeded persona, confirm the list renders and the wheel is clickable. (Full browser verification is Phase I.)

- [ ] **Step 5: Commit**

```bash
git add resources/mobile/views/Dashboard.vue
git commit -m "feat(m): unified next-actions list + wheel routes to achievements

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

### Task G2: Fyn KYC unlock bubble

**Files:**
- Modify: `resources/mobile/views/Dashboard.vue`

- [ ] **Step 1: Compute the top unlock item**

Add a computed:

```js
    topUnlock() {
      return this.nextActions.find((a) => a.type === 'unlock') || null;
    },
```

- [ ] **Step 2: Render a dismissible Fyn bubble (text only — no icons)**

Near the existing `md-fyn-nudge` markup, add (gated on not-dismissed):

```html
        <div v-if="topUnlock && !unlockBubbleDismissed" class="md-fyn-nudge md-fyn-nudge--unlock" role="status">
          <p class="md-fyn-nudge__text">I can help you {{ topUnlock.meta.toLowerCase() }} — want to?</p>
          <div class="md-fyn-nudge__actions">
            <button class="md-fyn-nudge__yes" @click="openFynForCapture(topUnlock.module)">Yes, help me</button>
            <button class="md-fyn-nudge__no" @click="unlockBubbleDismissed = true">Not now</button>
          </div>
        </div>
```

Add `unlockBubbleDismissed: false` to `data()`.

- [ ] **Step 3: Implement `openFynForCapture`**

Opens the Fyn chat (user's choice) pre-seeded with the capture intent for the module:

```js
    openFynForCapture(module) {
      const prompts = {
        protection: 'Help me add my protection cover details',
        savings: 'Help me add my savings details',
        investment: 'Help me add my investment details',
        retirement: 'Help me add my pension details',
        estate: 'Help me add my estate planning details',
        goals: 'Help me set a financial goal',
      };
      this.openFyn();
      this.send(prompts[module] || 'Help me add my financial details');
    },
```

(Reuses the existing `openFyn()` and `send()` — the chat opens and routes the write intent through the standard delegate-to-capture flow server-side.)

- [ ] **Step 4: Manual check — bubble appears for a gated persona, dismiss works, Yes opens chat**

- [ ] **Step 5: Commit**

```bash
git add resources/mobile/views/Dashboard.vue
git commit -m "feat(m): Fyn KYC unlock bubble (dismissible, opens pre-seeded chat)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

### Task G3: Lower the milestone banner

**Files:**
- Modify: `resources/mobile/views/dashboard.css`

- [ ] **Step 1: Adjust the `.md-milestone` rule (lines ~1694-1721)**

Lower it below the hero, constrain width, and ensure side insets clear the edges:

```css
.md-milestone {
  position: absolute;
  top: calc(8.5rem + env(safe-area-inset-top, 0px)); /* below the hero, not under the header */
  left: 1rem;
  right: 1rem;
  max-width: 26rem;
  margin: 0 auto;
  z-index: 8;
  /* keep existing background / padding / radius / shadow / animation lines below */
}
```

- [ ] **Step 2: Manual check — trigger a milestone, confirm all four sides visible**

(Milestones fire from `new_milestones`; to force one in dev, lower a net-worth threshold or use a persona that crosses one. Confirm visually in Phase I.)

- [ ] **Step 3: Commit**

```bash
git add resources/mobile/views/dashboard.css
git commit -m "fix(m): lower milestone banner below hero, constrain width

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase H — Achievements panel frontend

### Task H1: `Achievements.vue` + route

**Files:**
- Create: `resources/mobile/views/Achievements.vue`
- Modify: `resources/mobile/router.js`

- [ ] **Step 1: Create the view (two tabs; icons permitted here per Rule #15 carve-out)**

```vue
<template>
  <MobileLayout>
    <div class="ach">
      <header class="ach__head">
        <button class="ach__back" @click="$router.back()">Back</button>
        <h1 class="ach__title">Your progress</h1>
      </header>

      <div class="ach__tabs" role="tablist">
        <button :class="{ 'is-active': tab === 'achievements' }" @click="tab = 'achievements'" role="tab">Achievements</button>
        <button :class="{ 'is-active': tab === 'milestones' }" @click="tab = 'milestones'" role="tab">Milestones</button>
      </div>

      <section v-if="tab === 'achievements'" class="ach__panel">
        <h2 class="ach__subhead">Next</h2>
        <ul class="ach__list">
          <li v-for="n in next" :key="n.id" class="ach__next">
            <p class="ach__next-title">{{ n.title }}</p>
            <p class="ach__next-meta">{{ n.meta }}</p>
          </li>
          <li v-if="!next.length" class="ach__empty">You're all set for now.</li>
        </ul>

        <h2 class="ach__subhead">Earned</h2>
        <ul class="ach__list">
          <li v-for="a in achievements" :key="a.key" class="ach__badge" :class="{ 'is-earned': a.earned }">
            <p class="ach__badge-title">{{ a.title }}</p>
            <p class="ach__badge-desc">{{ a.description }}</p>
          </li>
        </ul>
      </section>

      <section v-else class="ach__panel">
        <ul class="ach__list">
          <li v-for="m in milestones" :key="m.key" class="ach__milestone">
            <p class="ach__badge-title">{{ m.title }}</p>
            <p class="ach__badge-desc" v-if="m.achieved_at">{{ formatDate(m.achieved_at) }}</p>
          </li>
          <li v-if="!milestones.length" class="ach__empty">No milestones reached yet — keep building your plan.</li>
        </ul>
      </section>
    </div>
  </MobileLayout>
</template>

<script>
import MobileLayout from '../components/MobileLayout.vue';
import { apiGet } from '../api.js';
import { store } from '../store.js';

export default {
  name: 'MobileAchievements',
  components: { MobileLayout },
  data() {
    return { tab: 'achievements', achievements: [], next: [], milestones: [] };
  },
  async mounted() {
    try {
      const res = await apiGet('/api/v1/mobile/achievements', store.token);
      const d = res.data?.data || {};
      this.achievements = d.achievements || [];
      this.next = d.next || [];
      this.milestones = d.milestones || [];
    } catch (e) { /* keep empty state */ }
  },
  methods: {
    formatDate(iso) {
      try { return new Date(iso).toLocaleDateString('en-GB'); } catch (e) { return ''; }
    },
  },
};
</script>

<style scoped>
.ach { padding: 1rem; }
.ach__head { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
.ach__title { font-size: 1.25rem; font-weight: 700; }
.ach__tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.ach__tabs button { flex: 1; padding: 0.6rem; border-radius: 0.75rem; border: none; background: var(--md-eggshell, #f5f3ef); font-weight: 600; }
.ach__tabs button.is-active { background: var(--md-horizon, #1f2a44); color: #fff; }
.ach__subhead { font-size: 0.95rem; font-weight: 700; margin: 1rem 0 0.5rem; }
.ach__list { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.6rem; }
.ach__next, .ach__badge, .ach__milestone { padding: 0.85rem; border-radius: 0.85rem; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.ach__badge { opacity: 0.55; }
.ach__badge.is-earned { opacity: 1; }
.ach__badge-title, .ach__next-title { font-weight: 600; }
.ach__badge-desc, .ach__next-meta { font-size: 0.8rem; color: #555; }
.ach__empty { padding: 0.85rem; color: #777; }
.ach__back { background: none; border: none; color: var(--md-raspberry, #e83e6d); font-weight: 600; }
</style>
```

NOTE: confirm the mobile layout component path — `ls resources/mobile/components/ | grep -i layout`. If it is not `MobileLayout.vue`, fix the import. If the mobile SPA has no layout wrapper component, render the markup without `<MobileLayout>` (the `/m` SPA chrome is provided by `App.vue`); check how `Dashboard.vue` is wrapped and match it.

- [ ] **Step 2: Verify the layout wrapper**

Run: `ls resources/mobile/components/ | grep -i layout; grep -n "Layout\|<template>" resources/mobile/views/Dashboard.vue | head`
Match whatever wrapper `Dashboard.vue` uses (Rule #13 — never ship a chrome-less page). Adjust the import/wrapper accordingly.

- [ ] **Step 3: Register the route**

In `resources/mobile/router.js`, add the import and route:

```js
import Achievements from './views/Achievements.vue';
// ... in routes array:
    { path: '/achievements', name: 'm-achievements', component: Achievements, meta: { auth: true } },
```

- [ ] **Step 4: Manual check — wheel tap navigates to the panel; tabs switch; data renders**

- [ ] **Step 5: Commit**

```bash
git add resources/mobile/views/Achievements.vue resources/mobile/router.js
git commit -m "feat(m): achievements panel view (achievements + milestones tabs)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

# Phase I — Build, verify, browser-test (Rule #14)

### Task I1: Backend test sweep

- [ ] **Step 1: Run all new + adjacent tests**

Run:
```bash
./vendor/bin/pest tests/Unit/Services/Mobile tests/Unit/Services/Coordination/RecommendationsAggregatorGatingTest.php tests/Feature/Mobile
```
Expected: all PASS. Fix any failures (Rule #14 — loop until green) before continuing.

- [ ] **Step 2: Commit any fixes**

```bash
git add -A && git commit -m "test: green the /m gamification + recommendations suite

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

### Task I2: Mobile build

- [ ] **Step 1: Build the mobile/web assets the supported way**

Run: `./deploy/mobile/build-ios.sh` (or the project's mobile web build) — NEVER raw `vite build`.
Then: `php artisan cache:clear` (mobile dashboard caches 5 min/user).

### Task I3: Browser verification (Playwright, interact — don't just snapshot)

- [ ] **Step 1: Persona A (student) on `/m`**

Log in as the student persona (local dev: fetch the verification code from the DB per CLAUDE.md), open `/m`:
- Wheel shows a non-Level-1 state and a real "X of Y actions" (not 0 of 0).
- "Ahead of X%" shows a value consistent with the student's low planning progress.
- Next-actions list shows ≤4 items; at least one **unlock** prompt; NO "discretionary trust" rec.
- The 4 box items match the list below.

- [ ] **Step 2: Persona B (retired couple) — verify DIFFERENT values**

Repeat for the retired persona: different wheel/level, different percentile, different actions (acceptance criterion #1, #5).

- [ ] **Step 3: Wheel → Achievements panel**

Tap the wheel → lands on `/m/app/achievements`; both tabs render; "Next" shows the same ≤4 actions; Milestones tab renders.

- [ ] **Step 4: Fyn unlock bubble**

Confirm the bubble appears for a gated persona; "Not now" dismisses it; "Yes, help me" opens the Fyn chat pre-seeded (no icons in the bubble).

- [ ] **Step 5: Milestone banner**

Trigger/observe a milestone banner; confirm it sits below the hero and all four sides are visible.

- [ ] **Step 6: Desktop no-regression**

Load the desktop recommendations view for a data-complete user → real recs still appear; for a sparse user → no generic estate-trust recs (the shared-gating change, acceptance #3).

- [ ] **Step 7: If any step is RED, loop**

Diagnose with file:line evidence → fix root cause → rebuild → re-verify. Repeat until every acceptance criterion in the spec (§9) holds. Reports come AFTER green.

---

## Self-review against the spec (run before handing off)

- §3 decisions A–J → A (PlanningProgressService C1), B (NextActionsService B1 + controller D2), C/D (Fyn bubble G2), E (Achievements E1/H1), F (one piece — this plan), G (shared gating A1), H (icons in Achievements H1), I (seeder F1), J (model = CSJ's `/model` action, noted). ✔
- §4.2 module coverage gap (investment via `InvestmentAgent`, goals via `GoalsAgent`) → Task A1 steps 3–7. ✔
- §4.7 milestone CSS → Task G3. ✔
- Acceptance §9 items 1–9 → Phase I steps. ✔
- No hardcoded tax (Rule #2): gates/ISA come from existing services; composite uses no tax literals. ✔
- Rule #13 (layout wrap): Task H1 step 2 verifies the wrapper. ✔
- Rule #15: icons only in Achievements (H1); Fyn bubble + dashboard list text-only. ✔

---

## Notes for the executor

- `claude-fable-5` is the intended model for this work — CSJ switches via `/model`; nothing in the code references it.
- Two model-name lookups are flagged inline (milestone model in C1/E1, tracking columns in F1) — do them before running those tasks; don't assume.
- The shared-aggregator gating affects desktop `/api/recommendations` by design — Phase I step 6 is the regression gate, not optional.
- Pre-existing `BelongsTo` null-array-offset deprecation in the estate path is out of scope — do not fix here.
