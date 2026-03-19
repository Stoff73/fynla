# Data Readiness & Prerequisite Gate Overhaul

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unify the three overlapping data tracking systems into a single source of truth so the AI agent, completeness endpoint, and journey progress all agree on what data exists and what's missing.

**Architecture:** PrerequisiteGateService becomes a thin delegation layer that calls the actual DataReadinessService for each module. The completeness endpoint enriches the response with field-level detail from DataReadinessServices. Journey progress calculates from real data completeness, not just steps clicked.

**Tech Stack:** Laravel 10, PHP 8.2, Vue.js 3 / Vuex

---

## File Structure

**Files to modify:**
- `app/Services/PrerequisiteGateService.php` — Refactor to delegate to DataReadiness services
- `app/Http/Controllers/Api/LifeStageController.php` — Enrich completeness endpoint with field-level detail
- `app/Services/LifeStage/LifeStageService.php` — Fix progress calculation to use data completeness
- `resources/js/store/modules/completeness.js` — Handle enriched response with field-level data

**Files to read (not modify):**
- `app/Services/Protection/ProtectionDataReadinessService.php`
- `app/Services/Savings/SavingsDataReadinessService.php`
- `app/Services/Retirement/RetirementDataReadinessService.php`
- `app/Services/Investment/Recommendation/DataReadinessService.php`
- `app/Services/Estate/EstateDataReadinessService.php`

---

### Task 1: Refactor PrerequisiteGateService to delegate to DataReadiness services

**Files:**
- Modify: `app/Services/PrerequisiteGateService.php`

This is the core change. Instead of duplicating blocking checks, delegate to the actual DataReadiness services.

- [ ] **Step 1: Add DataReadiness service dependencies**

Add constructor injection for all 5 DataReadiness services:

```php
public function __construct(
    private readonly ProtectionDataReadinessService $protectionReadiness,
    private readonly SavingsDataReadinessService $savingsReadiness,
    private readonly RetirementDataReadinessService $retirementReadiness,
    private readonly InvestmentDataReadinessService $investmentReadiness,
    private readonly EstateDataReadinessService $estateReadiness,
) {}
```

Add the use statements at the top of the file.

- [ ] **Step 2: Replace canAnalyseProtection with delegation**

Replace the manual check method with:

```php
public function canAnalyseProtection(User $user): array
{
    return $this->delegateToReadiness($this->protectionReadiness->assess($user), 'protection');
}
```

- [ ] **Step 3: Replace all 5 module gate methods with delegation**

Same pattern for savings, retirement, investment, estate. Each calls `$this->xxxReadiness->assess($user)` and passes result to `delegateToReadiness()`.

- [ ] **Step 4: Add the delegateToReadiness helper**

```php
private function delegateToReadiness(array $assessment, string $moduleName): array
{
    if ($assessment['can_proceed']) {
        return $this->pass();
    }

    $missing = [];
    $actions = [];

    foreach ($assessment['blocking'] as $check) {
        $missing[] = $check['message'];
        $actions[] = [
            'label' => $check['message'],
            'route' => $check['form_link'] ?? '/profile',
        ];
    }

    return $this->gate($missing, $actions, $moduleName);
}
```

- [ ] **Step 5: Keep goals and tax gates as-is**

Goals and tax_optimisation have no DataReadinessService — keep their existing manual checks.

- [ ] **Step 6: Remove duplicated helper methods**

Remove `calculateTotalIncome()` and `hasExpenditure()` — these are now handled by the DataReadiness services. Keep `deduplicateActions()`, `gate()`, and `pass()`.

- [ ] **Step 7: Add assessAll() method for enriched completeness**

```php
public function assessAll(User $user): array
{
    return [
        'protection' => $this->protectionReadiness->assess($user),
        'savings' => $this->savingsReadiness->assess($user),
        'retirement' => $this->retirementReadiness->assess($user),
        'investment' => $this->investmentReadiness->assess($user),
        'estate' => $this->estateReadiness->assess($user),
    ];
}
```

- [ ] **Step 8: Run existing Pest tests**

```bash
./vendor/bin/pest tests/Unit/Services/PrerequisiteGateServiceTest.php
```

Fix any failures caused by the constructor change (tests may need to inject mock DataReadiness services).

- [ ] **Step 9: Commit**

```bash
git add app/Services/PrerequisiteGateService.php
git commit -m "refactor: PrerequisiteGateService delegates to DataReadiness services"
```

---

### 🔒 CHECKPOINT 1 — Verify delegation works
Before proceeding to Task 2:
1. Run: `./vendor/bin/pest` — all existing tests must pass
2. Run: `php artisan route:list | head -3` — routes must load (no DI errors)
3. Verify: `php artisan tinker --execute="app(App\Services\PrerequisiteGateService::class)->enforce('protection', App\Models\User::first());"` — must return valid gate response

---

### Task 2: Enrich completeness endpoint with field-level detail

**Files:**
- Modify: `app/Http/Controllers/Api/LifeStageController.php`

The completeness endpoint currently returns module-level `can_advise` + `missing` (string list). Enrich it to include the full DataReadiness assessment per module.

- [ ] **Step 1: Inject PrerequisiteGateService (already injected) and use assessAll()**

Replace `buildModuleCompleteness()`:

```php
private function buildModuleCompleteness(User $user): array
{
    // Full assessments from DataReadiness services
    $assessments = $this->prerequisiteGate->assessAll($user);

    // Module gates (includes goals + tax which have no DataReadiness service)
    $gates = [
        'protection' => $this->prerequisiteGate->canAnalyseProtection($user),
        'savings' => $this->prerequisiteGate->canAnalyseSavings($user),
        'retirement' => $this->prerequisiteGate->canAnalyseRetirement($user),
        'investment' => $this->prerequisiteGate->canAnalyseInvestment($user),
        'estate' => $this->prerequisiteGate->canAnalyseEstate($user),
        'goals' => $this->prerequisiteGate->canAnalyseGoals($user),
        'tax_optimisation' => $this->prerequisiteGate->canAnalyseTax($user),
    ];

    // Display-level checks (existing logic, unchanged)
    $displayChecks = [
        'protection' => $user->lifeInsurancePolicies()->exists()
            || $user->criticalIllnessPolicies()->exists()
            || $user->incomeProtectionPolicies()->exists(),
        'savings' => $user->savingsAccounts()->exists(),
        'retirement' => $user->dcPensions()->exists()
            || $user->dbPensions()->exists()
            || $user->statePension()->exists(),
        'investment' => $user->investmentAccounts()->exists(),
        'estate' => $user->properties()->exists()
            || $user->investmentAccounts()->exists()
            || $user->savingsAccounts()->exists()
            || $user->liabilities()->exists(),
        'goals' => $user->goals()->exists(),
        'tax_optimisation' => $gates['tax_optimisation']['can_proceed'],
    ];

    $modules = [];
    foreach ($gates as $module => $gate) {
        $assessment = $assessments[$module] ?? null;
        $modules[$module] = [
            'has_data' => $displayChecks[$module] ?? false,
            'can_advise' => $gate['can_proceed'],
            'missing' => $gate['missing'],
            'guidance' => $gate['guidance'],
            'required_actions' => $gate['required_actions'],
            // Enriched: field-level detail from DataReadiness
            'completeness_percent' => $assessment['completeness_percent'] ?? null,
            'blocking' => $assessment['blocking'] ?? [],
            'warnings' => $assessment['warnings'] ?? [],
            'total_checks' => $assessment['total_checks'] ?? null,
            'passed_checks' => $assessment['passed_checks'] ?? null,
        ];
    }

    return $modules;
}
```

- [ ] **Step 2: Remove the duplicated calculateTotalIncome method**

The LifeStageController has its own `calculateTotalIncome()` — remove it since the gate service handles this now via DataReadiness services.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/LifeStageController.php
git commit -m "feat: enrich completeness endpoint with field-level DataReadiness detail"
```

---

### Task 3: Update buildCompletenessContext for richer AI prompt

**Files:**
- Modify: `app/Services/PrerequisiteGateService.php`

The AI prompt context should include field-level detail so the agent knows exactly what to ask for.

- [ ] **Step 1: Enrich buildCompletenessContext()**

```php
public function buildCompletenessContext(User $user): string
{
    $assessments = $this->assessAll($user);
    $gates = [
        'Protection' => $this->canAnalyseProtection($user),
        'Savings' => $this->canAnalyseSavings($user),
        'Retirement' => $this->canAnalyseRetirement($user),
        'Investment' => $this->canAnalyseInvestment($user),
        'Estate' => $this->canAnalyseEstate($user),
        'Goals' => $this->canAnalyseGoals($user),
        'Tax Optimisation' => $this->canAnalyseTax($user),
    ];

    $lines = [];
    foreach ($gates as $name => $gate) {
        $key = strtolower(str_replace(' ', '_', $name));
        $assessment = $assessments[$key] ?? null;

        if ($gate['can_proceed']) {
            $pct = $assessment['completeness_percent'] ?? 100;
            $warnings = count($assessment['warnings'] ?? []);
            $suffix = $warnings > 0 ? " ({$warnings} optional fields missing)" : '';
            $lines[] = "- {$name}: READY ({$pct}% complete{$suffix})";
        } else {
            $blockingItems = [];
            foreach (($assessment['blocking'] ?? []) as $check) {
                if (!$check['passed']) {
                    $blockingItems[] = $check['key'] . ': ' . $check['message'];
                }
            }
            $blockingList = !empty($blockingItems) ? implode('; ', $blockingItems) : implode(', ', $gate['missing']);
            $route = $gate['required_actions'][0]['route'] ?? '/profile';
            $lines[] = "- {$name}: BLOCKED -- {$blockingList} -- navigate user to: {$route}";
        }
    }

    return implode("\n", $lines);
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/PrerequisiteGateService.php
git commit -m "feat: enrich AI completeness context with field-level blocking detail"
```

---

### Task 4: Fix journey progress to use data completeness

**Files:**
- Modify: `app/Services/LifeStage/LifeStageService.php`

The progress calculation should reflect actual data entered, not just steps clicked.

- [ ] **Step 1: Read getDataCompleteness() to understand the 23 step checks**

This method already maps step IDs to real DB checks. The fix is to ensure this is the primary source for progress calculation, not `life_stage_completed_steps`.

- [ ] **Step 2: Update getProgress() to weight data completeness higher**

In `getProgress()`, merge the data-completed steps as the primary set, with explicit completed steps as a secondary overlay. The progress percentage should be: `(data_completed_steps / total_stage_steps) * 100`.

- [ ] **Step 3: Commit**

```bash
git add app/Services/LifeStage/LifeStageService.php
git commit -m "fix: journey progress reflects actual data completeness, not just steps clicked"
```

---

### Task 5: Update frontend completeness store to handle enriched data

**Files:**
- Modify: `resources/js/store/modules/completeness.js`

- [ ] **Step 1: Add new getters for field-level data**

```javascript
moduleCompleteness: (state) => (module) => {
    const mod = state.modules[module];
    return mod?.completeness_percent ?? 0;
},
moduleBlocking: (state) => (module) => {
    return state.modules[module]?.blocking ?? [];
},
moduleWarnings: (state) => (module) => {
    return state.modules[module]?.warnings ?? [];
},
overallCompleteness: (state) => {
    const modules = Object.values(state.modules);
    if (modules.length === 0) return 0;
    const total = modules.reduce((sum, m) => sum + (m.completeness_percent ?? 0), 0);
    return Math.round(total / modules.length);
},
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/store/modules/completeness.js
git commit -m "feat: completeness store exposes field-level blocking and warning data"
```

---

### 🔒 CHECKPOINT 2 — Full integration test
Before proceeding:
1. Run: `./vendor/bin/pest` — all tests pass
2. Run: `php artisan route:list | head -3` — routes load
3. Seed: `php artisan db:seed`
4. Browser: Login → verify dashboard loads
5. API test: `curl -s localhost:8000/api/life-stage/completeness` (with auth) — verify response includes `completeness_percent`, `blocking`, `warnings` per module
6. Verify AI prompt: `php artisan tinker --execute="app(App\Services\PrerequisiteGateService::class)->buildCompletenessContext(App\Models\User::first());"` — shows field-level detail

---

### Task 6: Write Pest tests for refactored PrerequisiteGateService

**Files:**
- Modify: `tests/Unit/Services/PrerequisiteGateServiceTest.php` (if exists) or create new

- [ ] **Step 1: Test that delegation produces same results as before**

Test each module gate with a user that has/doesn't have required data, verify `can_proceed` matches expected.

- [ ] **Step 2: Test assessAll() returns all 5 modules**

- [ ] **Step 3: Test buildCompletenessContext() includes field-level detail**

- [ ] **Step 4: Commit**

```bash
git add tests/
git commit -m "test: PrerequisiteGateService delegation and enriched completeness"
```

---

## Browser Test Checkpoints

### Checkpoint 1: After Task 1
- [ ] `./vendor/bin/pest` passes
- [ ] `php artisan route:list` loads (DI works)
- [ ] Tinker: enforce('protection', user) returns valid gate

### Checkpoint 2: After Tasks 2-5
- [ ] `./vendor/bin/pest` passes
- [ ] Dashboard loads in browser
- [ ] `/api/life-stage/completeness` returns enriched data with `completeness_percent` and `blocking` arrays
- [ ] AI prompt context shows field-level detail
- [ ] Frontend completeness store has new getters working
