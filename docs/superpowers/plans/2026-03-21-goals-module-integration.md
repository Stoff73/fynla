# Goals Module Integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire goals and life events into Savings, Protection, Estate, and Retirement module recommendation pipelines, and add goal contribution input with AI validation.

**Architecture:** Each module agent gains goals-awareness by injecting existing `GoalProgressService` / `GoalAffordabilityService` into their recommendation methods. No new services needed — the goal services already calculate progress, affordability, and life event impacts. The AI `create_goal` tool gains a `monthly_contribution` parameter with Fyn validation.

**Tech Stack:** Laravel 10 (PHP 8.2), Pest testing, Vue 3, Vuex

**Spec:** `docs/superpowers/specs/2026-03-21-goals-whatif-integration-design.md` (Sub-Project 2)

**Key conventions:**
- `declare(strict_types=1);` in all PHP files
- British spelling in user-facing text, American in code
- Agents use `$this->response(true, 'message', $data)` which returns `['success', 'message', 'data', 'timestamp']`
- Constructor injection with `private readonly` — never `app()` inside method bodies
- `SavingsAgent::generateRecommendations()` returns a **flat array** (not wrapped in `['data' => ...]`)
- `SavingsAgent::analyze()` returns a **plain array** (not wrapped in `$this->response()`)
- `ProtectionAgent::analyze()` returns via `$this->response()` — unwrap with `$result['data']`
- Mockery for service mocking, `Mockery::close()` in `afterEach()`
- All new test files must be added to `tests/Pest.php` scope declarations
- Run `php artisan db:seed` before any browser testing

**Critical model note:** `SavingsAgent` currently queries `SavingsGoal` (savings-specific goals). The `Goal` model is the main goals system. These are separate models. When adding goal-aware recommendations, query `Goal::forUserOrJoint()` directly — do not mix with the `SavingsGoal` path.

---

## File Structure

### Modified Files

| File | Change |
|------|--------|
| `app/Agents/SavingsAgent.php` | Inject `GoalProgressService`, add goal-behind-schedule + emergency fund + life event cash buffer recommendations |
| `app/Agents/ProtectionAgent.php` | Inject goal query, add goal commitments to coverage analysis |
| `app/Agents/EstateAgent.php` | Add goal liquidity risk to analysis output |
| `app/Agents/RetirementAgent.php` | Add post-retirement goals detection |
| `app/Services/AI/AiToolDefinitions.php` | Add `monthly_contribution` parameter to `create_goal` tool |
| `app/Traits/HasAiChat.php` | Enhance `create_goal` handler with contribution assessment |
| `resources/js/components/Goals/GoalCard.vue` | Add inline contribution input field |
| `tests/Pest.php` | Add new test files to scope declarations |

### New Files

| File | Purpose |
|------|---------|
| `tests/Unit/Agents/SavingsAgentGoalsTest.php` | Goal recommendation tests for SavingsAgent |
| `tests/Unit/Agents/ProtectionAgentGoalsTest.php` | Goal coverage tests for ProtectionAgent |
| `tests/Unit/Agents/EstateAgentGoalsTest.php` | Goal liquidity tests for EstateAgent |
| `tests/Unit/Agents/RetirementAgentGoalsTest.php` | Post-retirement goal tests for RetirementAgent |

---

## Task 0: Register New Test Files in Pest.php

**Files:**
- Modify: `tests/Pest.php`

- [ ] **Step 1: Add new test files to Pest.php scope**

In `tests/Pest.php`, find the `beforeEach` scope declaration that lists agent test files (around line 48). Add the 4 new test files:

```
'Unit/Agents/SavingsAgentGoalsTest.php',
'Unit/Agents/ProtectionAgentGoalsTest.php',
'Unit/Agents/EstateAgentGoalsTest.php',
'Unit/Agents/RetirementAgentGoalsTest.php',
```

- [ ] **Step 2: Commit**

```bash
git add tests/Pest.php
git commit -m "chore: register new agent goals test files in Pest.php"
```

---

## Task 1: SavingsAgent — Goal-Behind-Schedule Recommendations

`SavingsAgent` already queries `SavingsGoal` for savings-specific goals and tracks progress via `GoalProgressCalculator`. This task adds recommendations from the main `Goal` model when goals assigned to the savings module are behind schedule.

**Key facts about SavingsAgent:**
- `analyze()` returns a **plain array** (not wrapped in `$this->response()`)
- `generateRecommendations()` returns a **flat array** of recommendation objects
- Emergency fund data is at `$analysisData['emergency_fund']['runway_months']` (NOT `months_covered`)
- Uses inline `generateInlineRecommendations()` as fallback when no `SavingsActionDefinitionService`

**Files:**
- Create: `tests/Unit/Agents/SavingsAgentGoalsTest.php`
- Modify: `app/Agents/SavingsAgent.php`

- [ ] **Step 1: Write failing test — behind-schedule goal generates recommendation**

```php
<?php
declare(strict_types=1);

use App\Models\Goal;
use App\Models\Household;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'monthly_expenditure' => 2000,
        'annual_employment_income' => 40000,
    ]);
});

describe('SavingsAgent goal recommendations', function () {
    it('recommends increasing contributions for behind-schedule savings goal', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 5000,
        ]);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Holiday Fund',
            'target_amount' => 20000,
            'current_amount' => 5000,
            'target_date' => now()->addMonths(6),
            'assigned_module' => 'savings',
            'status' => 'active',
            'monthly_contribution' => 200,
        ]);

        $agent = app(\App\Agents\SavingsAgent::class);
        $analysis = $agent->analyze($this->user->id);

        // generateRecommendations returns a flat array
        $recommendations = $agent->generateRecommendations(
            array_merge($analysis, ['user_id' => $this->user->id])
        );

        $goalRecs = collect($recommendations)
            ->filter(fn ($r) => str_contains($r['description'] ?? '', 'Holiday Fund'));

        expect($goalRecs)->not->toBeEmpty();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Agents/SavingsAgentGoalsTest.php -v`
Expected: FAIL — no goal-specific recommendation generated yet

- [ ] **Step 3: Implement goal shortfall recommendations in SavingsAgent**

In `app/Agents/SavingsAgent.php`:

1. Add to constructor: `private readonly GoalProgressService $goalProgressService`
2. Add imports: `use App\Models\Goal;` and `use App\Services\Goals\GoalProgressService;`
3. In `generateInlineRecommendations()`, after the existing liquidity recommendations block, add:

```php
// Goal behind-schedule recommendations
$userId = $analysisData['user_id'] ?? 0;
if ($userId > 0) {
    $activeGoals = Goal::forUserOrJoint($userId)
        ->where('status', 'active')
        ->where('assigned_module', 'savings')
        ->get();

    foreach ($activeGoals as $goal) {
        $progress = $this->goalProgressService->calculateProgress($goal);
        if (!$progress['is_on_track'] && $progress['current_amount'] > 0) {
            $remaining = $progress['target_amount'] - $progress['current_amount'];
            $monthsLeft = max(1, $progress['days_remaining'] / 30);
            $requiredMonthly = round($remaining / $monthsLeft, 2);

            $recommendations[] = [
                'category' => 'goal_behind_schedule',
                'priority' => $goal->priority ?? 'medium',
                'title' => "{$goal->goal_name} is behind schedule",
                'description' => "Your {$goal->goal_name} goal needs {$this->formatCurrency($remaining)} more to reach its target. Consider increasing your monthly contribution to {$this->formatCurrency($requiredMonthly)} per month.",
                'action' => 'Increase monthly contribution',
            ];
        }
    }
}
```

Also pass `user_id` through in `generateRecommendations()` — ensure `$analysisData` includes it.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Agents/SavingsAgentGoalsTest.php -v`
Expected: PASS

- [ ] **Step 5: Write test — emergency fund goal suggestion**

Add to same test file:

```php
    it('suggests emergency fund goal when no goal exists and runway is insufficient', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 1000,
        ]);

        $agent = app(\App\Agents\SavingsAgent::class);
        $analysis = $agent->analyze($this->user->id);
        $recommendations = $agent->generateRecommendations(
            array_merge($analysis, ['user_id' => $this->user->id])
        );

        $efRecs = collect($recommendations)
            ->filter(fn ($r) => str_contains($r['description'] ?? '', 'emergency fund goal'));

        expect($efRecs)->not->toBeEmpty();
    });
```

- [ ] **Step 6: Implement emergency fund goal suggestion**

In the same goal recommendations block in `generateInlineRecommendations()`:

```php
    // Suggest emergency fund goal if none exists and runway is short
    $hasEmergencyFundGoal = Goal::forUserOrJoint($userId)
        ->where('goal_type', 'emergency_fund')
        ->where('status', 'active')
        ->exists();

    $runwayMonths = $analysisData['emergency_fund']['runway_months'] ?? 0;
    if (!$hasEmergencyFundGoal && $runwayMonths < 3) {
        $monthlyExpenditure = $analysisData['summary']['monthly_expenditure'] ?? 0;
        $targetAmount = $monthlyExpenditure * 3;
        if ($targetAmount > 0) {
            $recommendations[] = [
                'category' => 'create_emergency_fund_goal',
                'priority' => 'high',
                'title' => 'Create an emergency fund goal',
                'description' => "You have " . round($runwayMonths, 1) . " months of emergency savings. Consider creating an emergency fund goal of {$this->formatCurrency($targetAmount)} to cover 3 months of expenses.",
                'action' => 'Create emergency fund goal',
            ];
        }
    }
```

- [ ] **Step 7: Write test — life event cash buffer recommendation**

```php
    it('recommends cash buffer for upcoming expense life events', function () {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 5000,
        ]);

        \App\Models\LifeEvent::factory()->create([
            'user_id' => $this->user->id,
            'event_name' => 'Kitchen Renovation',
            'event_type' => 'home_improvement',
            'impact_type' => 'expense',
            'estimated_amount' => 25000,
            'event_date' => now()->addMonths(8),
            'certainty' => 'confirmed',
        ]);

        $agent = app(\App\Agents\SavingsAgent::class);
        $analysis = $agent->analyze($this->user->id);
        $recommendations = $agent->generateRecommendations(
            array_merge($analysis, ['user_id' => $this->user->id])
        );

        $eventRecs = collect($recommendations)
            ->filter(fn ($r) => str_contains($r['description'] ?? '', 'Kitchen Renovation'));

        expect($eventRecs)->not->toBeEmpty();
    });
```

- [ ] **Step 8: Implement life event cash buffer recommendation**

In the goal recommendations block, after emergency fund logic:

```php
    // Life event cash buffer recommendations (expense events within 12 months)
    $upcomingEvents = \App\Models\LifeEvent::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
        })
        ->where('impact_type', 'expense')
        ->where('event_date', '>', now())
        ->where('event_date', '<=', now()->addMonths(12))
        ->whereIn('certainty', ['confirmed', 'likely'])
        ->get();

    foreach ($upcomingEvents as $event) {
        $monthsUntil = max(1, now()->diffInMonths($event->event_date));
        $monthlySaving = round((float) $event->estimated_amount / $monthsUntil, 2);

        $recommendations[] = [
            'category' => 'life_event_cash_buffer',
            'priority' => 'high',
            'title' => "Build cash reserve for {$event->event_name}",
            'description' => "{$event->event_name} is expected in {$monthsUntil} months costing {$this->formatCurrency($event->estimated_amount)}. Consider saving {$this->formatCurrency($monthlySaving)} per month to prepare.",
            'action' => 'Set up savings for upcoming event',
        ];
    }
```

- [ ] **Step 9: Run all savings agent goal tests**

Run: `./vendor/bin/pest tests/Unit/Agents/SavingsAgentGoalsTest.php -v`
Expected: ALL PASS

- [ ] **Step 10: Run existing SavingsAgent tests for regressions**

Run: `./vendor/bin/pest tests/Unit/Agents/SavingsAgentTest.php -v`
Expected: ALL PASS

- [ ] **Step 11: Commit**

```bash
git add app/Agents/SavingsAgent.php tests/Unit/Agents/SavingsAgentGoalsTest.php
git commit -m "feat: SavingsAgent goal shortfall, emergency fund, and life event cash buffer recommendations"
```

---

## Task 2: ProtectionAgent — Goals in Coverage Calculation

ProtectionAgent calculates coverage needs based on income, debts, and dependants. Goal commitments should be flagged as additional coverage considerations.

**Key facts about ProtectionAgent:**
- `analyze()` returns via `$this->response()` — data at `$result['data']`
- `generateRecommendations()` expects the full response envelope (with nested `data` key) — pass `$result` not `$result['data']`
- Coverage gaps computed by `CoverageGapAnalyzer::calculateCoverageGap()`

**Files:**
- Create: `tests/Unit/Agents/ProtectionAgentGoalsTest.php`
- Modify: `app/Agents/ProtectionAgent.php`

- [ ] **Step 1: Read ProtectionAgent fully to understand analyze() and generateRecommendations() signatures**

Read `app/Agents/ProtectionAgent.php` — understand the full return structure and how `generateRecommendations` consumes `analyze` output.

- [ ] **Step 2: Write failing test — goals appear in analysis**

```php
<?php
declare(strict_types=1);

use App\Models\Goal;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'annual_employment_income' => 50000,
        'marital_status' => 'married',
    ]);
});

describe('ProtectionAgent goal integration', function () {
    it('includes goal commitments in analysis output', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Children Education Fund',
            'target_amount' => 45000,
            'current_amount' => 10000,
            'status' => 'active',
        ]);

        $agent = app(\App\Agents\ProtectionAgent::class);
        $result = $agent->analyze($this->user->id);

        expect($result['data'])->toHaveKey('goal_commitments');
        expect($result['data']['goal_commitments']['total_outstanding'])->toBe(35000.0);
    });
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Agents/ProtectionAgentGoalsTest.php -v`
Expected: FAIL — no `goal_commitments` key

- [ ] **Step 4: Implement goal commitments in ProtectionAgent::analyze()**

In `analyze()`, after the existing analysis, before the `return $this->response(...)`:

```php
$activeGoals = Goal::forUserOrJoint($userId)->where('status', 'active')->get();
$goalCommitments = [
    'total_outstanding' => round($activeGoals->sum(fn ($g) => max(0, (float) $g->target_amount - (float) $g->current_amount)), 2),
    'goals' => $activeGoals->map(fn ($g) => [
        'name' => $g->goal_name,
        'outstanding' => round(max(0, (float) $g->target_amount - (float) $g->current_amount), 2),
    ])->filter(fn ($g) => $g['outstanding'] > 0)->values()->toArray(),
    'count' => $activeGoals->count(),
    'coverage_note' => null,
];

if ($goalCommitments['total_outstanding'] > 0) {
    $count = $goalCommitments['count'];
    $goalCommitments['coverage_note'] = "You have {$count} active " . ($count === 1 ? 'goal' : 'goals') .
        " with {$this->formatCurrency($goalCommitments['total_outstanding'])} outstanding. Your protection cover should account for these commitments to ensure your family can meet these goals if the unexpected happens.";
}
```

Add `'goal_commitments' => $goalCommitments` to the data array passed to `$this->response()`.
Add `use App\Models\Goal;` import.

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Agents/ProtectionAgentGoalsTest.php -v`
Expected: PASS

- [ ] **Step 6: Run existing ProtectionAgent tests for regressions**

Run: `./vendor/bin/pest tests/Unit/Agents/ProtectionAgentTest.php -v`
Expected: ALL PASS

- [ ] **Step 7: Commit**

```bash
git add app/Agents/ProtectionAgent.php tests/Unit/Agents/ProtectionAgentGoalsTest.php
git commit -m "feat: ProtectionAgent includes goal commitments in coverage analysis"
```

---

## Task 3: EstateAgent — Goal Liquidity Risk

**Files:**
- Create: `tests/Unit/Agents/EstateAgentGoalsTest.php`
- Modify: `app/Agents/EstateAgent.php`

- [ ] **Step 1: Read EstateAgent analyze() to understand return structure**

Read `app/Agents/EstateAgent.php` — find where the data array is built and returned via `$this->response()`.

- [ ] **Step 2: Write failing test**

```php
<?php
declare(strict_types=1);

use App\Models\Goal;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
    ]);
});

describe('EstateAgent goal integration', function () {
    it('flags goal liquidity risk in estate analysis', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Bridge Fund',
            'target_amount' => 200000,
            'current_amount' => 50000,
            'status' => 'active',
        ]);

        $agent = app(\App\Agents\EstateAgent::class);
        $result = $agent->analyze($this->user->id);

        expect($result['data'])->toHaveKey('goal_liquidity');
        expect($result['data']['goal_liquidity']['total_outstanding'])->toBe(150000.0);
    });
});
```

- [ ] **Step 3: Run test to verify it fails, then implement**

Add goal liquidity calculation to `EstateAgent::analyze()` before the `$this->response()` return:

```php
$activeGoals = Goal::forUserOrJoint($userId)->where('status', 'active')->get();
$goalLiquidity = [
    'total_outstanding' => round($activeGoals->sum(fn ($g) => max(0, (float) $g->target_amount - (float) $g->current_amount)), 2),
    'goals' => $activeGoals->map(fn ($g) => [
        'name' => $g->goal_name,
        'outstanding' => round(max(0, (float) $g->target_amount - (float) $g->current_amount), 2),
    ])->filter(fn ($g) => $g['outstanding'] > 0)->values()->toArray(),
];
```

Add `'goal_liquidity' => $goalLiquidity` to the response data. Add `use App\Models\Goal;`.

- [ ] **Step 4: Run test to verify it passes + regression check**

Run: `./vendor/bin/pest tests/Unit/Agents/EstateAgentGoalsTest.php tests/Unit/Agents/EstateAgentTest.php -v`
Expected: ALL PASS

- [ ] **Step 5: Commit**

```bash
git add app/Agents/EstateAgent.php tests/Unit/Agents/EstateAgentGoalsTest.php
git commit -m "feat: EstateAgent flags goal liquidity risk in estate analysis"
```

---

## Task 4: RetirementAgent — Post-Retirement Goal Detection

**Files:**
- Create: `tests/Unit/Agents/RetirementAgentGoalsTest.php`
- Modify: `app/Agents/RetirementAgent.php`

- [ ] **Step 1: Read RetirementAgent analyze() return structure**

Read `app/Agents/RetirementAgent.php` — find how the response data is built.

- [ ] **Step 2: Write failing test**

```php
<?php
declare(strict_types=1);

use App\Models\Goal;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'date_of_birth' => now()->subYears(55),
        'target_retirement_age' => 60,
    ]);
});

describe('RetirementAgent goal integration', function () {
    it('identifies goals that extend beyond retirement age', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Grandchildren Fund',
            'target_amount' => 100000,
            'current_amount' => 20000,
            'target_date' => now()->addYears(10),
            'status' => 'active',
            'monthly_contribution' => 500,
        ]);

        $agent = app(\App\Agents\RetirementAgent::class);
        $result = $agent->analyze($this->user->id);

        expect($result['data'])->toHaveKey('post_retirement_goals');
        expect($result['data']['post_retirement_goals'])->not->toBeEmpty();
        expect($result['data']['post_retirement_goals'][0]['name'])->toBe('Grandchildren Fund');
    });
});
```

- [ ] **Step 3: Run test to verify it fails, then implement**

In `RetirementAgent::analyze()`, before the response return:

```php
$retirementAge = $user->target_retirement_age ?? 67;
$currentAge = $user->date_of_birth ? (int) now()->diffInYears($user->date_of_birth) : null;
$postRetirementGoals = [];

if ($currentAge) {
    $yearsToRetirement = max(0, $retirementAge - $currentAge);
    $retirementDate = now()->addYears($yearsToRetirement);

    $activeGoals = Goal::forUserOrJoint($userId)->where('status', 'active')->get();
    foreach ($activeGoals as $goal) {
        if ($goal->target_date && $goal->target_date->gt($retirementDate)) {
            $postRetirementGoals[] = [
                'name' => $goal->goal_name,
                'target_amount' => round((float) $goal->target_amount, 2),
                'outstanding' => round(max(0, (float) $goal->target_amount - (float) $goal->current_amount), 2),
                'monthly_contribution' => round((float) ($goal->monthly_contribution ?? 0), 2),
                'annual_cost' => round((float) ($goal->monthly_contribution ?? 0) * 12, 2),
                'target_date' => $goal->target_date->format('Y-m-d'),
            ];
        }
    }
}
```

Add `'post_retirement_goals' => $postRetirementGoals` to the response data. Add `use App\Models\Goal;`.

- [ ] **Step 4: Run test to verify it passes + regression check**

Run: `./vendor/bin/pest tests/Unit/Agents/RetirementAgentGoalsTest.php tests/Unit/Agents/RetirementAgentTest.php -v`
Expected: ALL PASS

- [ ] **Step 5: Commit**

```bash
git add app/Agents/RetirementAgent.php tests/Unit/Agents/RetirementAgentGoalsTest.php
git commit -m "feat: RetirementAgent identifies post-retirement goal funding needs"
```

---

## Task 5: AI Tool — Add monthly_contribution to create_goal

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Traits/HasAiChat.php`

- [ ] **Step 1: Read the create_goal tool definition and handler**

Read `app/Services/AI/AiToolDefinitions.php` to find the `create_goal` tool properties. Read `app/Traits/HasAiChat.php` and search for `create_goal` in the `executeTool` method to find the handler.

- [ ] **Step 2: Add monthly_contribution to tool definition**

In `AiToolDefinitions.php`, in the `create_goal` tool `properties` array, add:

```php
'monthly_contribution' => [
    'type' => 'number',
    'description' => 'Optional monthly contribution amount in pounds. If provided, Fyn will assess whether this is sufficient to reach the target by the deadline.',
],
```

Do NOT add it to the `required` array.

- [ ] **Step 3: Update create_goal handler to save and assess contribution**

In `HasAiChat.php`, in the `create_goal` handler, after the goal is created and saved:

```php
if (isset($params['monthly_contribution']) && $params['monthly_contribution'] > 0) {
    $goal->monthly_contribution = $params['monthly_contribution'];
    $goal->save();

    $affordabilityService = app(\App\Services\Goals\GoalAffordabilityService::class);
    $progressService = app(\App\Services\Goals\GoalProgressService::class);

    $affordability = $affordabilityService->analyzeAffordability($goal, $user);
    $progress = $progressService->calculateProgress($goal);

    $toolResult['contribution_assessment'] = [
        'monthly_amount' => $params['monthly_contribution'],
        'is_on_track' => $progress['is_on_track'],
        'status' => $progress['status'],
        'affordability_category' => $affordability['category'] ?? 'unknown',
        'required_monthly' => $affordability['required_monthly'] ?? null,
    ];
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/AI/AiToolDefinitions.php app/Traits/HasAiChat.php
git commit -m "feat: create_goal AI tool accepts monthly_contribution with affordability assessment"
```

---

## Task 6: GoalCard.vue — Inline Contribution Input

**Files:**
- Modify: `resources/js/components/Goals/GoalCard.vue`

- [ ] **Step 1: Read GoalCard.vue fully**

Read the component to understand the current template structure, data properties, emits, and where the contribution display should go.

- [ ] **Step 2: Add contribution display/edit section**

After the progress bar section, add:

```vue
<!-- Monthly Contribution -->
<div v-if="showActions" class="mt-3 pt-3 border-t border-light-gray">
  <div v-if="!editingContribution" class="flex items-center justify-between">
    <span class="text-xs text-neutral-500">Monthly contribution</span>
    <div class="flex items-center gap-2">
      <span class="text-sm font-medium text-horizon-500">
        {{ goal.monthly_contribution ? formatCurrency(goal.monthly_contribution) : 'Not set' }}
      </span>
      <button
        @click.stop="editingContribution = true"
        class="text-xs text-raspberry-500 hover:text-raspberry-600"
      >
        {{ goal.monthly_contribution ? 'Edit' : 'Set' }}
      </button>
    </div>
  </div>
  <div v-else class="flex items-center gap-2" @click.stop>
    <div class="relative flex-1">
      <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
        <span class="text-neutral-500 text-xs">£</span>
      </div>
      <input
        v-model.number="contributionAmount"
        type="number"
        min="0"
        step="1"
        class="input-field pl-5 py-1 text-sm"
        placeholder="0"
        @keyup.enter="saveContribution"
      />
    </div>
    <button @click.stop="saveContribution" class="text-xs font-medium text-spring-600 hover:text-spring-700">Save</button>
    <button @click.stop="cancelContribution" class="text-xs text-neutral-500 hover:text-neutral-600">Cancel</button>
  </div>
</div>
```

Add to component `data()`:
```javascript
editingContribution: false,
contributionAmount: this.goal.monthly_contribution || 0,
```

Add methods:
```javascript
saveContribution() {
  this.$emit('update-contribution', {
    goalId: this.goal.id,
    monthly_contribution: this.contributionAmount,
  });
  this.editingContribution = false;
},
cancelContribution() {
  this.contributionAmount = this.goal.monthly_contribution || 0;
  this.editingContribution = false;
},
```

Add `'update-contribution'` to emits. Note `@click.stop` on interactive elements to prevent the card's click handler from firing.

- [ ] **Step 3: Verify compilation**

Check Vite dev server for errors. Navigate to Goals page as a preview persona to verify the card renders correctly.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Goals/GoalCard.vue
git commit -m "feat: GoalCard inline monthly contribution input with edit/save"
```

---

## Task 7: Integration Test & Final Verification

- [ ] **Step 1: Run all new agent goal tests**

```bash
./vendor/bin/pest tests/Unit/Agents/SavingsAgentGoalsTest.php tests/Unit/Agents/ProtectionAgentGoalsTest.php tests/Unit/Agents/EstateAgentGoalsTest.php tests/Unit/Agents/RetirementAgentGoalsTest.php -v
```

Expected: ALL PASS

- [ ] **Step 2: Run all existing agent tests for regressions**

```bash
./vendor/bin/pest tests/Unit/Agents/ -v
```

Expected: ALL PASS

- [ ] **Step 3: Run full test suite**

```bash
./vendor/bin/pest
```

Expected: ALL PASS

- [ ] **Step 4: Format check**

```bash
./vendor/bin/pint --test
```

Expected: PASS

- [ ] **Step 5: Seed and verify locally**

```bash
php artisan db:seed
```

Log in as David Mitchell (peak_earners) via demo selector. Navigate to Goals page. Verify:
- Goal cards show monthly contribution display
- Contribution edit/save works
- Behind-schedule banner shows specific goal names

- [ ] **Step 6: Final commit if needed**

```bash
git commit -m "chore: goals module integration — all tests passing"
```
