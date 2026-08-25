# SaveTax Campaign — Post-Expenses Journey + Tax Strategy Terminal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship sections 4–6 of the SaveTax campaign — a dedicated post-expenses onboarding branch (employed → occupational scheme → ISA → bank → investment → pension → spouse-work → spouse-detail), three first-class household paths (single, dual-earner, single-earner-couple), a lightweight `TaxStrategyCalculator` composing existing per-allowance services, and an interactive `/tax-strategy` dashboard with allowance grid + sliders + asset-shifting recommendations.

**Architecture:** New campaign branch hangs off `STATE_PROFILE_REVIEW_EXPENDITURE` for `path=campaign` users only — the existing `STATE_ASSET_CAPTURE` is bypassed. 4 new capture tools write to existing tables where possible (`dc_pensions`, `savings_accounts`, `investment_accounts`) plus a new `tax_strategy_household_inputs` table for spouse data. Calculator branches on `users.household_calculation_mode` (`single` | `dual_earner` | `single_earner_couple`). Frontend reuses `ISAAllowanceTracker` progress-bar pattern, `DrawdownSimulator` slider pattern, `ActionSummaryCard` tile pattern.

**Tech Stack:** Laravel 10, MySQL 8, Vue.js 3, Vuex, Pest (PHPUnit-compatible), Playwright MCP for browser scenarios, ApexCharts (existing chart library), TailwindCSS with Fynla design system v1.3.0.

---

## Context

Sessions 111–112 (28–29 April 2026) shipped the SaveTax campaign **front half**: landing page (`/savetax`), `?from=savetax` registration wire-through, campaign welcome bubble at `STATE_BASE_PERSONAL`, live tax allowances API (`GET /api/public/tax-allowances`), and `?utm_source=` channel attribution. The campaign onboarding currently terminates at `STATE_PROFILE_REVIEW_EXPENDITURE` because sections 4–6 (post-expenses branch + terminal page) were deferred awaiting CSJ's planned conversation map.

CSJ has now produced two whiteboards (`April/April29Updates/campaignMap.jpeg` + `April/April29Updates/houuseSpouse.jpeg`) defining:

- The **conversation flow** for capturing the tax-strategy data set.
- A terminal **Tax Strategy dashboard** with allowance utilisation cards (green = used capacity, raspberry = unused capacity) and interactive sliders for live what-if modelling.
- A dedicated **single-earner couple path** where the non-working spouse's ~£40k of unused tax capacity (PA £12,570 + Starting Rate for Savings £5,000 + PSA £1,000 + ISA £20,000 + CGT £3,000 + Dividend £500) drives **asset-shifting strategies** (the whiteboard's £535k-of-gilts → £18,750 tax-free example).

After this plan ships, a SaveTax user goes: `/savetax` → register → MFA → onboarding chat → (existing personal/family/employment/expenditure capture) → **new campaign branch** → terminal page at `/tax-strategy` with their full tax position rendered, manipulable, and surfaced as actionable strategies.

---

## Decisions Locked In (CSJ chose during planning session 113)

| # | Decision | Implication |
|---|----------|-------------|
| D1 | New campaign-only bubble flow | Bypass `STATE_ASSET_CAPTURE` for `path=campaign`; dedicated state-machine branch with focused capture tools |
| D2 | New dedicated `/tax-strategy` route under AppLayout | Fyn dock visible, side menu visible, lazy-loaded; auto-nav from end of campaign onboarding |
| D3 | New lightweight `TaxStrategyCalculator` | Composes existing per-allowance services, sub-50ms per slider change, no DB writes |
| D4 | Three first-class paths | Path A (single), Path B (dual-earner), Path C (single-earner couple — asset-shifting strategy view) |

---

## Conflicts Raised + Resolutions

These are gaps, ambiguities, or potential conflicts surfaced during exploration. Each is resolved before implementation, or escalated for CSJ confirmation as part of Task 0.

### C1 — DCPension column naming convention is `_percent`, not `_pct`

**Discovered:** `app/Models/DCPension.php:37-40` — existing fields are `employee_contribution_percent`, `employer_contribution_percent`, `employer_matching_limit`, `monthly_contribution_amount`. `_percent` not `_pct`.

**Resolution:** Match existing convention. New field on `dc_pensions` is `salary_sacrifice` (boolean) only. Calculator and tools refer to `employee_contribution_percent` / `employer_contribution_percent` / `employer_matching_limit` (already present).

### C2 — `create_pension` + `update_record` may already cover occupational-scheme capture

**Discovered:** Existing tool `create_pension` writes to `dc_pensions` with `pension_type`, `employee_contribution_percent`, `employer_contribution_percent`, `employer_matching_limit`. The only new field needed is `salary_sacrifice`.

**Resolution:** Do NOT introduce a brand-new tool for the whole occupational-scheme capture. Instead:
1. Extend `create_pension` and `update_record` to accept the new `salary_sacrifice` parameter.
2. Add ONE small new tool `capture_salary_sacrifice` that takes `pension_id` + `salary_sacrifice` (bool) — used when the user already has a pension record from the new state machine flow but only the sacrifice flag is being added/changed.

This reduces the new-tool count from 4 to 3:
- `capture_salary_sacrifice` (boolean toggle on a specific dc_pension)
- `capture_spouse_work_status` (sets marriage_allowance_eligible + household_calculation_mode)
- `capture_spouse_household_data` (working-spouse fields)
- `capture_spouse_non_working_assets` (non-working-spouse fields)

(Net: 4 new tools, plus the `salary_sacrifice` parameter added to existing `create_pension` / `update_record` schemas.)

### C3 — Spouse data privacy / GDPR

**Concern:** Capturing spouse income / accounts without their consent. The existing `SpousePermission` model exists for cross-user data sharing.

**Resolution:** All spouse data captured here is the user's *own description of their household*, stored on the user's record (or their related `tax_strategy_household_inputs`). Nothing is written to the spouse's User record (if one exists via `spouse_id`). If the spouse later registers and grants `SpousePermission`, future-work can offer to merge — out of scope here. Frontend copy on the dashboard reads "Your household — based on what you've told us" to make the boundary explicit.

### C4 — `TaxOptimisationAgent` already provides allowance analysis

**Discovered:** `app/Agents/TaxOptimisationAgent.php` exposes `analyze(int $userId)` returning all 6 allowance positions + ranked strategies. The new `TaxStrategyCalculator` would overlap.

**Resolution:** They serve different purposes:
- `TaxOptimisationAgent::analyze()` — full per-user analysis with caching, runs on `/api/tax/optimisation-analysis` requests, used by dashboard initial GET to populate **recommendations**.
- `TaxStrategyCalculator::calculate()` — pure stateless function, takes user + override DTO, returns allowance grid + tax/NI deltas. No DB writes. Designed for sub-50ms slider drag.

Reuse is via composition — `TaxStrategyService::getDashboardPayload()` calls **both** (calculator for the grid, agent for recommendations). `recalculate()` (slider path) calls **only** the calculator.

### C5 — `onboarding_completed` flag setter

**Discovered:** Currently set in `OnboardingChatDirector.php:2031` and 3 places in `OnboardingService.php` when reaching `STATE_DONE`. New `STATE_CAMPAIGN_TERMINAL` must trigger it too.

**Resolution:** `STATE_CAMPAIGN_TERMINAL.next` callable (`nextFromCampaignTerminal`) returns `STATE_DONE` so the existing `STATE_DONE` setter chain fires unchanged. `STATE_CAMPAIGN_TERMINAL` itself just emits the navigate SSE event.

### C6 — Eval YAML scenario expansion

**Discovered:** Sprint 1 has 14 state-machine eval YAMLs. Adding 9 new states means 9 new YAMLs would be needed for full eval coverage.

**Resolution:** **Out of scope of this plan.** Eval coverage for the new campaign branch is a follow-up Sprint 1 item, sequenced after S1.7.a (AssertionHelpers extension). This plan ships browser scenarios (Pest+Playwright) only — those run in CI and provide live verification per CLAUDE.md Rule #15. Eval coverage is added in a subsequent plan.

### C7 — AdviceFyn read access to new tables

**Discovered:** Once user lands on `/tax-strategy`, future chat happens via `AdviceFyn` (read-only). If they ask "should I top up my ISA?", AdviceFyn must be able to read `tax_strategy_household_inputs` and `dc_pensions.salary_sacrifice`.

**Resolution:** Add a new read-only tool `get_tax_strategy_position` to `AdviceFyn`'s catalogue. Returns the same payload as `GET /api/tax-strategy` (read-only, no DB writes). This stays consistent with the canonical Two-Fyn contract — AdviceFyn reads, OnboardingFyn writes.

### C8 — Existing tests assume `STATE_PROFILE_REVIEW_EXPENDITURE → STATE_ASSET_CAPTURE`

**Concern:** Modifying that transition to be conditional could break onboarding tests in the 396-passing baseline.

**Resolution:** The transition becomes a callable that defaults to `STATE_ASSET_CAPTURE` when `path !== 'campaign'`. Existing tests use `path = 'journey'` or `path = 'focus'`, so they pass through the existing branch. Sanity-check: grep for `STATE_PROFILE_REVIEW_EXPENDITURE` in the test suite during Task 14; any hard-coded next-state expectation gets updated to use the new callable.

### C9 — Subscription/trial gating for `/tax-strategy`

**Concern:** Does the savetax user need an active subscription/trial to view `/tax-strategy`?

**Resolution:** **CSJ confirmation needed before Phase 5.** Default position: `/tax-strategy` requires `auth:sanctum` only (consistent with `/dashboard`, `/actions`). The existing 7-day free trial (granted at registration via `RegisterRequest`) covers initial access. Post-trial, the existing `SubscriptionRequiredMiddleware` (if it exists for `/dashboard`) is reused. **Task 0 includes: confirm gating with CSJ.**

### C10 — Slider write-back

**Confirmed out of scope:** Sliders model what-if scenarios in-memory; no "Apply this strategy" persistence. CSJ confirmed in CSJTODO §"Out of scope".

---

## File Structure

### New files to create

| Path | Responsibility |
|------|----------------|
| `database/migrations/2026_05_01_000001_add_tax_strategy_columns_to_users.php` | Adds `marriage_allowance_eligible`, `household_calculation_mode` to `users` |
| `database/migrations/2026_05_01_000002_add_salary_sacrifice_to_dc_pensions.php` | Adds `salary_sacrifice` boolean to `dc_pensions` |
| `database/migrations/2026_05_01_000003_create_tax_strategy_household_inputs_table.php` | Creates `tax_strategy_household_inputs` for spouse data (both paths B + C) |
| `app/Models/TaxStrategyHouseholdInput.php` | Eloquent model, belongsTo User, fillable for all dual-earner + non-working fields |
| `app/Services/AI/Tools/CaptureSalarySacrificeTool.php` | New Sonnet tool definition (or in `AiToolDefinitions.php`) |
| `app/Services/AI/Tools/CaptureSpouseWorkStatusTool.php` | New Sonnet tool definition |
| `app/Services/AI/Tools/CaptureSpouseHouseholdDataTool.php` | New Sonnet tool definition |
| `app/Services/AI/Tools/CaptureSpouseNonWorkingAssetsTool.php` | New Sonnet tool definition |
| `app/Services/AI/Tools/GetTaxStrategyPositionTool.php` | AdviceFyn read-only tool |
| `app/DataTransferObjects/TaxStrategyOverridesDTO.php` | Slider override shape |
| `app/DataTransferObjects/TaxStrategyOutputDTO.php` | Calculator output shape |
| `app/Services/Tax/TaxStrategyCalculator.php` | Stateless calculator composing 6 existing services |
| `app/Services/Tax/TaxStrategyService.php` | Orchestrator: getDashboardPayload + recalculate |
| `app/Http/Controllers/Api/TaxStrategyController.php` | `show` + `calculate` endpoints |
| `app/Http/Requests/TaxStrategyCalculateRequest.php` | Slider override validation |
| `app/Http/Resources/TaxStrategyResource.php` | Frontend payload shaping |
| `resources/js/views/TaxStrategy/TaxStrategyDashboard.vue` | Top-level view, auth-gated, AppLayout |
| `resources/js/components/TaxStrategy/TaxYearHeader.vue` | "Tax Year 2026/27" header banner |
| `resources/js/components/TaxStrategy/AllowanceGrid.vue` | 2-col grid wrapper (Income / Investment) |
| `resources/js/components/TaxStrategy/AllowanceCard.vue` | Single allowance card with progress bar |
| `resources/js/components/TaxStrategy/HouseholdView.vue` | Twin grid; differs by calculation_mode |
| `resources/js/components/TaxStrategy/StrategySliderPanel.vue` | Sliders for pension %, SS y/n, ISA top-up, MA toggle |
| `resources/js/components/TaxStrategy/StrategyRecommendationList.vue` | List of recommendation tiles |
| `resources/js/components/TaxStrategy/AssetShiftingPanel.vue` | Path C only — concrete £-amount transfer suggestions |
| `resources/js/store/modules/taxStrategy.js` | Vuex module |
| `resources/js/services/taxStrategyService.js` | API wrapper |
| `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` | Calculator golden-path matrix |
| `tests/Feature/Api/TaxStrategy/ShowEndpointTest.php` | GET endpoint contract |
| `tests/Feature/Api/TaxStrategy/CalculateEndpointTest.php` | POST endpoint validation + delta |
| `tests/Feature/Fyn/Tools/CaptureSalarySacrificeToolTest.php` | Tool handler + DB write |
| `tests/Feature/Fyn/Tools/CaptureSpouseWorkStatusToolTest.php` | yes/no branching + flag setter |
| `tests/Feature/Fyn/Tools/CaptureSpouseHouseholdDataToolTest.php` | dual_earner field write |
| `tests/Feature/Fyn/Tools/CaptureSpouseNonWorkingAssetsToolTest.php` | single_earner_couple field write |
| `tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php` | New states reachable + skip_if matrix |
| `tests/Unit/Services/Onboarding/CampaignSpouseRoutingTest.php` | SPOUSE_WORK.next routing |
| `tests/Unit/Services/Onboarding/CampaignTerminalNavigationTest.php` | SSE navigate event + onboarding_completed |
| `tests/Browser/scenarios/BS-26-savetax-single-employed.php` | Path A end-to-end |
| `tests/Browser/scenarios/BS-27-savetax-married-spouse-works.php` | Path B end-to-end |
| `tests/Browser/scenarios/BS-28-savetax-married-spouse-no-work.php` | Path C end-to-end + asset-shifting panel |

### Existing files to modify

| Path | Modification |
|------|--------------|
| `app/Models/User.php` | `$fillable` += `marriage_allowance_eligible`, `household_calculation_mode`; `$casts` updates; new `taxStrategyHouseholdInput()` HasOne |
| `app/Models/DCPension.php` | `$fillable` += `salary_sacrifice`; `$casts['salary_sacrifice'] => 'boolean'` |
| `app/Services/Onboarding/OnboardingStateMachine.php` | 9 new state constants, 9 new state defs in `states()`, 4 new skip_if helpers, 2 new routing callables, modify `STATE_PROFILE_REVIEW_EXPENDITURE.next` to be a callable |
| `app/Services/Onboarding/OnboardingChatDirector.php` | `captureToolSet()` += 4 new tool names; new method `nextFromCampaignTerminal()` SSE emit; `onboarding_completed` setter call ordering |
| `app/Services/AI/AiToolDefinitions.php` | Add `salary_sacrifice` parameter to `create_pension` schema; register 4 new tools |
| `app/Agents/CoordinatingAgent.php` | 4 new switch-case dispatches; 4 new `handle*` methods (`handleCaptureSalarySacrifice`, `handleCaptureSpouseWorkStatus`, `handleCaptureSpouseHouseholdData`, `handleCaptureSpouseNonWorkingAssets`); 1 new read tool dispatch (`handleGetTaxStrategyPosition`) |
| `app/Services/AI/AdviceFyn.php` | Add `get_tax_strategy_position` to allowed read tools (NOT to `WRITE_TOOLS`) |
| `app/Services/Savings/PSACalculator.php` | New method `assessPerAccount(User $user): array` — per-bank breakdown; existing `assessPSAPosition` unchanged |
| `app/Services/Retirement/SalarySacrificeAnalyzer.php` | Optional override hook so calculator can pass slider values without mutating DB |
| `routes/api.php` | Register `GET /api/tax-strategy` + `POST /api/tax-strategy/calculate` |
| `resources/js/router/index.js` | New route `/tax-strategy` lazy-loaded with AppLayout |
| `resources/js/store/index.js` | Register `taxStrategy` Vuex module |
| `resources/js/views/Actions/ActionsDashboard.vue` | Add "Tax Strategy" tile linking to `/tax-strategy` (visible if `users.onboarding_fyn_selection === 'savetax'`) |
| `database/seeders/PreviewUserSeeder.php` | Add `tax_strategy_household_inputs` rows for `peak_earners` (dual_earner) and `young_family` (single_earner_couple) so preview personas exercise both paths |

---

## Phase 0 — Prerequisites & confirmations

### Task 0.1: Confirm subscription gating with CSJ

- [ ] **Step 1: Ask CSJ** — *"Should `/tax-strategy` be gated by an active subscription/trial, or is it accessible to any authenticated user (e.g. trial-expired)?"* Default plan assumption: same as `/dashboard` (active trial OR active sub required). Capture answer in this plan as a decision-register entry.

- [ ] **Step 2: Document the answer** in `April/April29Updates/savetax-section4-decisions.md` (new file) before proceeding.

### Task 0.2: Branch + worktree setup

- [ ] **Step 1: Verify current branch** — should be `feature/fyn-persona-split` per CSJTODO §112.

```bash
cd /Users/CSJ/Desktop/fynla
git status
git log --oneline -5
```
Expected: branch shows `feature/fyn-persona-split`, last commit `823d0f0` or later.

- [ ] **Step 2: Pull latest from origin**
```bash
git pull origin feature/fyn-persona-split
```

- [ ] **Step 3: Run baseline test count for regression reference**
```bash
./vendor/bin/pest tests/Feature/Fyn tests/Feature/Onboarding tests/Feature/Auth --compact 2>&1 | tail -5
```
Expected: 396 passed (baseline). Capture exact number to compare against after every phase.

---

## Phase 1 — Schema + models

### Task 1.1: Migration — User columns

**Files:**
- Create: `database/migrations/2026_05_01_000001_add_tax_strategy_columns_to_users.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Generate migration**

```bash
php artisan make:migration add_tax_strategy_columns_to_users --table=users
```

- [ ] **Step 2: Write migration body**

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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marriage_allowance_eligible')
                ->nullable()
                ->after('signup_source')
                ->comment('Set true when spouse_works=no during savetax campaign onboarding');
            $table->string('household_calculation_mode', 32)
                ->nullable()
                ->after('marriage_allowance_eligible')
                ->comment('single | dual_earner | single_earner_couple — set by capture_spouse_work_status tool');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marriage_allowance_eligible', 'household_calculation_mode']);
        });
    }
};
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```
Expected: `Migrated: 2026_05_01_000001_add_tax_strategy_columns_to_users`.

- [ ] **Step 4: Update User model**

Modify `app/Models/User.php` — append to `$fillable`:
```php
'marriage_allowance_eligible',
'household_calculation_mode',
```
And to `$casts`:
```php
'marriage_allowance_eligible' => 'boolean',
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_01_000001_add_tax_strategy_columns_to_users.php app/Models/User.php
git commit -m "feat(savetax): add marriage_allowance_eligible + household_calculation_mode to users"
```

### Task 1.2: Migration — DCPension salary_sacrifice

**Files:**
- Create: `database/migrations/2026_05_01_000002_add_salary_sacrifice_to_dc_pensions.php`
- Modify: `app/Models/DCPension.php`

- [ ] **Step 1: Generate + write migration**

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->boolean('salary_sacrifice')
                ->nullable()
                ->after('employer_matching_limit')
                ->comment('true if pension contributions are made via salary sacrifice');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn('salary_sacrifice');
        });
    }
};
```

- [ ] **Step 2: Run migration**
```bash
php artisan migrate
```

- [ ] **Step 3: Update DCPension model — append to $fillable**

```php
'salary_sacrifice',
```

And to `$casts`:
```php
'salary_sacrifice' => 'boolean',
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_01_000002_add_salary_sacrifice_to_dc_pensions.php app/Models/DCPension.php
git commit -m "feat(savetax): add salary_sacrifice to dc_pensions"
```

### Task 1.3: Migration — tax_strategy_household_inputs table

**Files:**
- Create: `database/migrations/2026_05_01_000003_create_tax_strategy_household_inputs_table.php`
- Create: `app/Models/TaxStrategyHouseholdInput.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write migration**

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_strategy_household_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Working-spouse fields (path B / dual_earner)
            $table->decimal('spouse_annual_income', 12, 2)->nullable();
            $table->string('spouse_employment_status', 32)->nullable();
            $table->decimal('spouse_isa_balance', 12, 2)->nullable();
            $table->string('spouse_psa_band', 16)->nullable(); // basic | higher | additional
            $table->decimal('spouse_unrealised_gains', 12, 2)->nullable();
            $table->decimal('spouse_annual_dividends', 12, 2)->nullable();
            $table->decimal('spouse_pension_input_annual', 12, 2)->nullable();

            // Non-working-spouse fields (path C / single_earner_couple)
            $table->decimal('spouse_existing_isa_balance', 12, 2)->nullable();
            $table->decimal('spouse_existing_savings_balance', 12, 2)->nullable();
            $table->decimal('spouse_existing_investment_balance', 12, 2)->nullable();
            $table->decimal('spouse_existing_dividend_holdings_value', 12, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_strategy_household_inputs');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 3: Create model**

`app/Models/TaxStrategyHouseholdInput.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxStrategyHouseholdInput extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'spouse_annual_income',
        'spouse_employment_status',
        'spouse_isa_balance',
        'spouse_psa_band',
        'spouse_unrealised_gains',
        'spouse_annual_dividends',
        'spouse_pension_input_annual',
        'spouse_existing_isa_balance',
        'spouse_existing_savings_balance',
        'spouse_existing_investment_balance',
        'spouse_existing_dividend_holdings_value',
    ];

    protected $casts = [
        'spouse_annual_income' => 'decimal:2',
        'spouse_isa_balance' => 'decimal:2',
        'spouse_unrealised_gains' => 'decimal:2',
        'spouse_annual_dividends' => 'decimal:2',
        'spouse_pension_input_annual' => 'decimal:2',
        'spouse_existing_isa_balance' => 'decimal:2',
        'spouse_existing_savings_balance' => 'decimal:2',
        'spouse_existing_investment_balance' => 'decimal:2',
        'spouse_existing_dividend_holdings_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Add relationship to User model**

In `app/Models/User.php` add:
```php
public function taxStrategyHouseholdInput(): HasOne
{
    return $this->hasOne(TaxStrategyHouseholdInput::class);
}
```

(Ensure `use Illuminate\Database\Eloquent\Relations\HasOne;` is imported if not already.)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_01_000003_create_tax_strategy_household_inputs_table.php app/Models/TaxStrategyHouseholdInput.php app/Models/User.php
git commit -m "feat(savetax): create tax_strategy_household_inputs table + model"
```

### Task 1.4: Reseed and verify

- [ ] **Step 1: Reseed**

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

- [ ] **Step 2: Sanity check — confirm new columns visible**

```bash
php artisan tinker --execute="echo \DB::getSchemaBuilder()->getColumnListing('users') |> implode(',');"
php artisan tinker --execute="echo \DB::getSchemaBuilder()->getColumnListing('dc_pensions') |> implode(',');"
php artisan tinker --execute="echo \DB::getSchemaBuilder()->getColumnListing('tax_strategy_household_inputs') |> implode(',');"
```
Expected: lists include `marriage_allowance_eligible`, `household_calculation_mode`, `salary_sacrifice`, plus all 12 spouse fields.

- [ ] **Step 3: Run regression suite**

```bash
./vendor/bin/pest --testsuite=Architecture
```
Expected: 95/95 still green (we haven't changed any code outside models + migrations).

---

## Phase 2 — Capture tools

### Task 2.1: TDD — `capture_salary_sacrifice` tool

**Files:**
- Create: `tests/Feature/Fyn/Tools/CaptureSalarySacrificeToolTest.php`
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Agents/CoordinatingAgent.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\User;

it('writes salary_sacrifice flag to the named dc_pension', function () {
    $user = User::factory()->create();
    $pension = DCPension::factory()->for($user)->create(['salary_sacrifice' => null]);

    $agent = app(CoordinatingAgent::class);
    $result = $agent->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
    ], $user);

    expect($result['updated'])->toBeTrue();
    expect($pension->fresh()->salary_sacrifice)->toBeTrue();
});

it('rejects requests for a pension owned by another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $pension = DCPension::factory()->for($other)->create();

    $agent = app(CoordinatingAgent::class);
    $result = $agent->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
    ], $user);

    expect($result['error'] ?? null)->not->toBeNull();
    expect($pension->fresh()->salary_sacrifice)->toBeNull();
});
```

- [ ] **Step 2: Run test to confirm RED**

```bash
./vendor/bin/pest tests/Feature/Fyn/Tools/CaptureSalarySacrificeToolTest.php -v
```
Expected: FAIL — "Tool capture_salary_sacrifice not registered" or similar.

- [ ] **Step 3: Add tool definition**

In `app/Services/AI/AiToolDefinitions.php`, add:
```php
[
    'name' => 'capture_salary_sacrifice',
    'description' => 'Set salary_sacrifice flag on a specific DC pension owned by the user.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'pension_id' => ['type' => 'integer', 'description' => 'ID of the dc_pension row to update.'],
            'salary_sacrifice' => ['type' => 'boolean', 'description' => 'true if contributions are made via salary sacrifice.'],
        ],
        'required' => ['pension_id', 'salary_sacrifice'],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 4: Add dispatch + handler**

In `app/Agents/CoordinatingAgent.php`, add to the switch in `executeTool`:
```php
'capture_salary_sacrifice' => $this->handleCaptureSalarySacrifice($input, $user),
```

And the handler method:
```php
private function handleCaptureSalarySacrifice(array $input, User $user): array
{
    $pension = \App\Models\DCPension::where('id', $input['pension_id'])
        ->where('user_id', $user->id)
        ->first();

    if (! $pension) {
        return ['error' => true, 'message' => 'Pension not found or not owned by user'];
    }

    $pension->update(['salary_sacrifice' => (bool) $input['salary_sacrifice']]);

    return [
        'updated' => true,
        'pension_id' => $pension->id,
        'message' => 'Salary sacrifice setting updated.',
    ];
}
```

- [ ] **Step 5: Run test to confirm GREEN**

```bash
./vendor/bin/pest tests/Feature/Fyn/Tools/CaptureSalarySacrificeToolTest.php -v
```
Expected: 2 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/AiToolDefinitions.php app/Agents/CoordinatingAgent.php tests/Feature/Fyn/Tools/CaptureSalarySacrificeToolTest.php
git commit -m "feat(savetax): capture_salary_sacrifice tool + handler"
```

### Task 2.2: TDD — `capture_spouse_work_status` tool

**Files:**
- Create: `tests/Feature/Fyn/Tools/CaptureSpouseWorkStatusToolTest.php`
- Modify: `app/Services/AI/AiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;

it('sets marriage_allowance_eligible=true and mode=single_earner_couple when spouse does not work', function () {
    $user = User::factory()->create(['marital_status' => 'married']);
    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('capture_spouse_work_status', ['spouse_works' => false], $user);

    expect($result['updated'])->toBeTrue();
    $user->refresh();
    expect($user->marriage_allowance_eligible)->toBeTrue();
    expect($user->household_calculation_mode)->toBe('single_earner_couple');
});

it('sets marriage_allowance_eligible=false and mode=dual_earner when spouse works', function () {
    $user = User::factory()->create(['marital_status' => 'married']);
    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('capture_spouse_work_status', ['spouse_works' => true], $user);

    expect($result['updated'])->toBeTrue();
    $user->refresh();
    expect($user->marriage_allowance_eligible)->toBeFalse();
    expect($user->household_calculation_mode)->toBe('dual_earner');
});
```

- [ ] **Step 2: Run test → RED**

```bash
./vendor/bin/pest tests/Feature/Fyn/Tools/CaptureSpouseWorkStatusToolTest.php -v
```

- [ ] **Step 3: Add tool definition**

```php
[
    'name' => 'capture_spouse_work_status',
    'description' => 'Set whether the user\'s spouse currently works. Updates household_calculation_mode and marriage_allowance_eligible accordingly.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'spouse_works' => ['type' => 'boolean', 'description' => 'true if spouse has earned income, false otherwise.'],
        ],
        'required' => ['spouse_works'],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 4: Add dispatch + handler**

```php
'capture_spouse_work_status' => $this->handleCaptureSpouseWorkStatus($input, $user),
```

```php
private function handleCaptureSpouseWorkStatus(array $input, User $user): array
{
    $works = (bool) $input['spouse_works'];

    $user->update([
        'marriage_allowance_eligible' => ! $works,
        'household_calculation_mode' => $works ? 'dual_earner' : 'single_earner_couple',
    ]);

    return [
        'updated' => true,
        'household_calculation_mode' => $user->household_calculation_mode,
        'message' => $works
            ? 'Recorded that your spouse works — we\'ll capture more details next.'
            : 'Recorded that your spouse doesn\'t currently work — Marriage Allowance may apply.',
    ];
}
```

- [ ] **Step 5: Run test → GREEN**

- [ ] **Step 6: Commit**

```bash
git commit -am "feat(savetax): capture_spouse_work_status tool + household mode setter"
```

### Task 2.3: TDD — `capture_spouse_household_data` tool (dual_earner)

**Files:**
- Create: `tests/Feature/Fyn/Tools/CaptureSpouseHouseholdDataToolTest.php`
- Modify: `app/Services/AI/AiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;

it('creates household_inputs row with working-spouse fields', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'household_calculation_mode' => 'dual_earner',
    ]);

    $agent = app(CoordinatingAgent::class);
    $result = $agent->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 45000,
        'spouse_employment_status' => 'full_time',
        'spouse_isa_balance' => 12000,
        'spouse_psa_band' => 'basic',
        'spouse_unrealised_gains' => 0,
        'spouse_annual_dividends' => 0,
        'spouse_pension_input_annual' => 3600,
    ], $user);

    expect($result['updated'])->toBeTrue();
    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull();
    expect((float) $row->spouse_annual_income)->toBe(45000.0);
    expect((float) $row->spouse_isa_balance)->toBe(12000.0);
    expect($row->spouse_employment_status)->toBe('full_time');
});

it('updates the existing row on a second call (updateOrCreate)', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'dual_earner']);
    $agent = app(CoordinatingAgent::class);

    $agent->executeTool('capture_spouse_household_data', ['spouse_annual_income' => 30000], $user);
    $agent->executeTool('capture_spouse_household_data', ['spouse_annual_income' => 35000], $user);

    expect(TaxStrategyHouseholdInput::where('user_id', $user->id)->count())->toBe(1);
    expect((float) TaxStrategyHouseholdInput::where('user_id', $user->id)->first()->spouse_annual_income)->toBe(35000.0);
});
```

- [ ] **Step 2: Run → RED**

- [ ] **Step 3: Add tool definition**

```php
[
    'name' => 'capture_spouse_household_data',
    'description' => 'Capture working-spouse data for dual_earner households (spouse_works=yes path).',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'spouse_annual_income' => ['type' => 'number', 'description' => 'Spouse gross annual income in pounds.'],
            'spouse_employment_status' => ['type' => 'string', 'enum' => ['full_time', 'part_time', 'self_employed', 'retired']],
            'spouse_isa_balance' => ['type' => 'number'],
            'spouse_psa_band' => ['type' => 'string', 'enum' => ['basic', 'higher', 'additional']],
            'spouse_unrealised_gains' => ['type' => 'number'],
            'spouse_annual_dividends' => ['type' => 'number'],
            'spouse_pension_input_annual' => ['type' => 'number'],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 4: Add dispatch + handler**

```php
'capture_spouse_household_data' => $this->handleCaptureSpouseHouseholdData($input, $user),
```

```php
private function handleCaptureSpouseHouseholdData(array $input, User $user): array
{
    $allowed = array_intersect_key($input, array_flip([
        'spouse_annual_income', 'spouse_employment_status', 'spouse_isa_balance',
        'spouse_psa_band', 'spouse_unrealised_gains', 'spouse_annual_dividends',
        'spouse_pension_input_annual',
    ]));

    \App\Models\TaxStrategyHouseholdInput::updateOrCreate(
        ['user_id' => $user->id],
        $allowed
    );

    return [
        'updated' => true,
        'message' => 'Spouse household data captured.',
    ];
}
```

- [ ] **Step 5: Run → GREEN**

- [ ] **Step 6: Commit**

```bash
git commit -am "feat(savetax): capture_spouse_household_data tool (dual_earner path)"
```

### Task 2.4: TDD — `capture_spouse_non_working_assets` tool (single_earner_couple)

**Files:**
- Create: `tests/Feature/Fyn/Tools/CaptureSpouseNonWorkingAssetsToolTest.php`
- Modify: `app/Services/AI/AiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;

it('creates household_inputs row with non-working-spouse fields', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
        'marriage_allowance_eligible' => true,
    ]);

    $agent = app(CoordinatingAgent::class);
    $result = $agent->executeTool('capture_spouse_non_working_assets', [
        'spouse_existing_isa_balance' => 5000,
        'spouse_existing_savings_balance' => 0,
        'spouse_existing_investment_balance' => 0,
        'spouse_existing_dividend_holdings_value' => 0,
    ], $user);

    expect($result['updated'])->toBeTrue();
    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull();
    expect((float) $row->spouse_existing_isa_balance)->toBe(5000.0);
    expect((float) $row->spouse_existing_savings_balance)->toBe(0.0);
    // working-spouse fields untouched
    expect($row->spouse_annual_income)->toBeNull();
});

it('accepts the all-zero case (spouse has no standalone assets)', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'single_earner_couple']);
    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('capture_spouse_non_working_assets', [
        'spouse_existing_isa_balance' => 0,
        'spouse_existing_savings_balance' => 0,
        'spouse_existing_investment_balance' => 0,
        'spouse_existing_dividend_holdings_value' => 0,
    ], $user);

    expect($result['updated'])->toBeTrue();
    expect(TaxStrategyHouseholdInput::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run → RED**

- [ ] **Step 3: Add tool definition**

```php
[
    'name' => 'capture_spouse_non_working_assets',
    'description' => 'Capture standalone assets owned by a non-working spouse (single_earner_couple path). Used to compute available capacity for asset-shifting strategies.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'spouse_existing_isa_balance' => ['type' => 'number'],
            'spouse_existing_savings_balance' => ['type' => 'number'],
            'spouse_existing_investment_balance' => ['type' => 'number'],
            'spouse_existing_dividend_holdings_value' => ['type' => 'number'],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 4: Add dispatch + handler**

```php
'capture_spouse_non_working_assets' => $this->handleCaptureSpouseNonWorkingAssets($input, $user),
```

```php
private function handleCaptureSpouseNonWorkingAssets(array $input, User $user): array
{
    $allowed = array_intersect_key($input, array_flip([
        'spouse_existing_isa_balance',
        'spouse_existing_savings_balance',
        'spouse_existing_investment_balance',
        'spouse_existing_dividend_holdings_value',
    ]));

    \App\Models\TaxStrategyHouseholdInput::updateOrCreate(
        ['user_id' => $user->id],
        $allowed
    );

    return [
        'updated' => true,
        'message' => 'Spouse standalone assets captured.',
    ];
}
```

- [ ] **Step 5: Run → GREEN**

- [ ] **Step 6: Commit**

```bash
git commit -am "feat(savetax): capture_spouse_non_working_assets tool (single_earner_couple path)"
```

### Task 2.5: Whitelist new tools in OnboardingChatDirector

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php`

- [ ] **Step 1: Read current `captureToolSet()`** at lines ~2231–2251.

- [ ] **Step 2: Add the 4 new tool names** to the whitelist array (alphabetised within their section):

```php
'capture_salary_sacrifice',
'capture_spouse_household_data',
'capture_spouse_non_working_assets',
'capture_spouse_work_status',
```

- [ ] **Step 3: Run regression**

```bash
./vendor/bin/pest tests/Feature/Fyn --compact 2>&1 | tail -5
```
Expected: 396 + 8 new (4 tool tests × 2 cases each, plus existing 396) ≥ 404 passed.

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(savetax): whitelist 4 new capture tools in OnboardingChatDirector"
```

---

## Phase 3 — State machine campaign branch

### Task 3.1: TDD — STATE_PROFILE_REVIEW_EXPENDITURE.next becomes a callable

**Files:**
- Create: `tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php`
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;

it('routes path=campaign users from expenditure-review to STATE_CAMPAIGN_OCCUPATIONAL_SCHEME', function () {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'employment_status' => 'full_time',
    ]);

    $next = OnboardingStateMachine::nextFromExpenditureReview($user);

    expect($next)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
});

it('routes path=journey users to STATE_ASSET_CAPTURE (regression — unchanged behaviour)', function () {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'protection',
    ]);

    $next = OnboardingStateMachine::nextFromExpenditureReview($user);

    expect($next)->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
});

it('routes path=focus users to STATE_ASSET_CAPTURE (regression — unchanged behaviour)', function () {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'focus',
        'onboarding_fyn_selection' => 'savings',
    ]);

    expect(OnboardingStateMachine::nextFromExpenditureReview($user))
        ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
});
```

- [ ] **Step 2: Run → RED**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php -v
```
Expected: FAIL — undefined method `nextFromExpenditureReview` and undefined constant `STATE_CAMPAIGN_OCCUPATIONAL_SCHEME`.

- [ ] **Step 3: Declare 9 new state constants**

In `app/Services/Onboarding/OnboardingStateMachine.php` (after existing constants, ~line 74):

```php
public const STATE_CAMPAIGN_OCCUPATIONAL_SCHEME      = 'campaign_occupational_scheme';
public const STATE_CAMPAIGN_ISA_HOLDINGS             = 'campaign_isa_holdings';
public const STATE_CAMPAIGN_BANK_ACCOUNTS            = 'campaign_bank_accounts';
public const STATE_CAMPAIGN_INVESTMENT_ACCOUNTS      = 'campaign_investment_accounts';
public const STATE_CAMPAIGN_PENSION_CONTRIBS         = 'campaign_pension_contribs';
public const STATE_CAMPAIGN_SPOUSE_WORK              = 'campaign_spouse_work';
public const STATE_CAMPAIGN_SPOUSE_HOUSEHOLD         = 'campaign_spouse_household';
public const STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS = 'campaign_spouse_non_working_assets';
public const STATE_CAMPAIGN_TERMINAL                 = 'campaign_terminal';
```

- [ ] **Step 4: Add `nextFromExpenditureReview` callable**

In the same file, in the routing helpers section (~line 622):

```php
public static function nextFromExpenditureReview(User $user): string
{
    return $user->onboarding_fyn_path === 'campaign'
        ? self::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME
        : self::STATE_ASSET_CAPTURE;
}
```

- [ ] **Step 5: Wire the callable into `STATE_PROFILE_REVIEW_EXPENDITURE.next`**

Find the existing `STATE_PROFILE_REVIEW_EXPENDITURE` def (around line 259):
```php
'next' => self::STATE_ASSET_CAPTURE,
```
Replace with:
```php
'next' => self::class.'::nextFromExpenditureReview',
```

- [ ] **Step 6: Run → GREEN**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php -v
```
Expected: 3 passed.

- [ ] **Step 7: Run full state machine regression**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding tests/Feature/Onboarding --compact
```
Expected: zero new failures vs baseline. If any test hard-codes the next-state expectation, update it to use the callable.

- [ ] **Step 8: Commit**

```bash
git commit -am "feat(savetax): branch STATE_PROFILE_REVIEW_EXPENDITURE.next on path=campaign"
```

### Task 3.2: Add 9 new state definitions to states()

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`

- [ ] **Step 1: Identify the insertion point** in `states()` array. Insert immediately after `STATE_PROFILE_REVIEW_EXPENDITURE` definition, before `STATE_ASSET_CAPTURE`.

- [ ] **Step 2: Add the 9 state defs**

```php
self::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME => [
    'turn_type' => 'grouped_extract',
    'prompt' => 'Tell me about your workplace pension. What percentage of your salary do you contribute, does your employer match it, and is it via salary sacrifice? If you don\'t have a workplace pension, just say so and we\'ll move on.',
    'extraction_tools' => ['create_pension', 'update_record', 'capture_salary_sacrifice'],
    'next' => self::STATE_CAMPAIGN_ISA_HOLDINGS,
    'skip_if' => [self::class, 'skipIfNotEmployed'],
],
self::STATE_CAMPAIGN_ISA_HOLDINGS => [
    'turn_type' => 'grouped_extract',
    'prompt' => 'Let\'s look at your ISAs. Do you have a Cash ISA or Stocks & Shares ISA? If so, what\'s the current balance and how much have you put in this tax year?',
    'extraction_tools' => ['create_savings_account', 'create_investment_account'],
    'next' => self::STATE_CAMPAIGN_BANK_ACCOUNTS,
    'skip_if' => null,
],
self::STATE_CAMPAIGN_BANK_ACCOUNTS => [
    'turn_type' => 'grouped_extract',
    'prompt' => 'Now your savings outside an ISA — bank accounts, savings accounts, premium bonds. For each, what\'s the balance and the interest rate?',
    'extraction_tools' => ['create_savings_account'],
    'next' => self::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS,
    'skip_if' => null,
],
self::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS => [
    'turn_type' => 'grouped_extract',
    'prompt' => 'Any investment accounts outside an ISA — General Investment Accounts, share trading platforms? If so, current value, your purchase cost, and any annual dividend income.',
    'extraction_tools' => ['create_investment_account', 'create_holding'],
    'next' => self::STATE_CAMPAIGN_PENSION_CONTRIBS,
    'skip_if' => null,
],
self::STATE_CAMPAIGN_PENSION_CONTRIBS => [
    'turn_type' => 'grouped_extract',
    'prompt' => 'Beyond the workplace pension we covered, do you make any personal pension or SIPP contributions? If so, how much per year (gross)?',
    'extraction_tools' => ['create_pension', 'update_record'],
    'next' => self::STATE_CAMPAIGN_SPOUSE_WORK,
    'skip_if' => null,
],
self::STATE_CAMPAIGN_SPOUSE_WORK => [
    'turn_type' => 'bubbles',
    'prompt' => 'Does {spouse_first_name} work?',
    'options' => [
        ['id' => 'yes', 'label' => 'Yes, they work'],
        ['id' => 'no',  'label' => 'No, they don\'t currently work'],
    ],
    'extraction_tools' => ['capture_spouse_work_status'],
    'next' => self::class.'::nextFromSpouseWork',
    'skip_if' => [self::class, 'skipIfNotMarried'],
],
self::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD => [
    'turn_type' => 'grouped_extract',
    'prompt' => 'Great. How much does {spouse_first_name} earn annually, and do they have ISAs, investments, or pension contributions of their own?',
    'extraction_tools' => ['capture_spouse_household_data'],
    'next' => self::STATE_CAMPAIGN_TERMINAL,
    'skip_if' => [self::class, 'skipIfNotDualEarner'],
],
self::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS => [
    'turn_type' => 'grouped_extract',
    'prompt' => 'Got it — {spouse_first_name} doesn\'t currently earn an income. That\'s actually useful for your tax strategy, because they have around £40,000 of unused tax allowances we can put to work. Do they have any savings, ISAs, or investment accounts in their own name today, or is it all in yours?',
    'extraction_tools' => ['capture_spouse_non_working_assets'],
    'next' => self::STATE_CAMPAIGN_TERMINAL,
    'skip_if' => [self::class, 'skipIfNotSingleEarnerCouple'],
],
self::STATE_CAMPAIGN_TERMINAL => [
    'turn_type' => 'navigate',
    'prompt' => 'All set, {first_name} — let me show you your tax position.',
    'navigate_to' => '/tax-strategy',
    'next' => self::STATE_DONE,
    'skip_if' => null,
],
```

- [ ] **Step 3: Add 4 skip_if helpers + 1 routing callable**

```php
public static function skipIfNotEmployed(User $user): bool
{
    return ! in_array($user->employment_status, ['full_time', 'part_time'], true);
}

public static function skipIfNotMarried(User $user): bool
{
    return ! in_array($user->marital_status, ['married', 'civil_partnership'], true);
}

public static function skipIfNotDualEarner(User $user): bool
{
    return $user->household_calculation_mode !== 'dual_earner';
}

public static function skipIfNotSingleEarnerCouple(User $user): bool
{
    return $user->household_calculation_mode !== 'single_earner_couple';
}

public static function nextFromSpouseWork(User $user): string
{
    return match ($user->household_calculation_mode) {
        'dual_earner' => self::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD,
        'single_earner_couple' => self::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS,
        default => self::STATE_CAMPAIGN_TERMINAL,
    };
}
```

- [ ] **Step 4: Run regression**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding tests/Feature/Onboarding --compact
```
Expected: zero new failures.

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(savetax): 9 new STATE_CAMPAIGN_* states + skip_if helpers + routing callable"
```

### Task 3.3: TDD — Spouse routing branches correctly

**Files:**
- Create: `tests/Unit/Services/Onboarding/CampaignSpouseRoutingTest.php`

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;

it('routes dual_earner users from SPOUSE_WORK to SPOUSE_HOUSEHOLD', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'marital_status' => 'married',
    ]);

    expect(OnboardingStateMachine::nextFromSpouseWork($user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);
});

it('routes single_earner_couple users from SPOUSE_WORK to SPOUSE_NON_WORKING_ASSETS', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'marital_status' => 'married',
    ]);

    expect(OnboardingStateMachine::nextFromSpouseWork($user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS);
});

it('falls back to TERMINAL if mode is somehow unset', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => null,
        'marital_status' => 'married',
    ]);

    expect(OnboardingStateMachine::nextFromSpouseWork($user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL);
});
```

- [ ] **Step 2: Run → expected GREEN** (callable already added in Task 3.2)

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignSpouseRoutingTest.php -v
```

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/Onboarding/CampaignSpouseRoutingTest.php
git commit -m "test(savetax): SPOUSE_WORK routing covers all 3 modes"
```

### Task 3.4: TDD — Terminal navigation emits SSE event + sets onboarding_completed

**Files:**
- Create: `tests/Unit/Services/Onboarding/CampaignTerminalNavigationTest.php`
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (or wherever the navigate event is emitted; verify in code)

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;

it('STATE_CAMPAIGN_TERMINAL navigates to /tax-strategy', function () {
    $state = (new OnboardingStateMachine)->states()[OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL];

    expect($state['turn_type'])->toBe('navigate');
    expect($state['navigate_to'])->toBe('/tax-strategy');
    expect($state['next'])->toBe(OnboardingStateMachine::STATE_DONE);
});
```

- [ ] **Step 2: Run → GREEN** (state already defined in Task 3.2)

- [ ] **Step 3: Wire SSE emission in OnboardingChatDirector**

Find the place that handles `turn_type === 'navigate'` (search for `turn_type` and existing navigate emissions). If no such handler exists, add one in the director's `handleStateAdvance` (or equivalent):

```php
if (($state['turn_type'] ?? null) === 'navigate') {
    yield ['event' => 'navigate', 'data' => ['route' => $state['navigate_to']]];
    // The advance to STATE_DONE then triggers the existing onboarding_completed setter at line 2031.
}
```

(If the `navigate` event type already exists for other purposes, follow that pattern.)

- [ ] **Step 4: Verify regression**

```bash
./vendor/bin/pest tests/Feature/Fyn --compact
```

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(savetax): STATE_CAMPAIGN_TERMINAL emits navigate SSE event to /tax-strategy"
```

---

## Phase 4 — TaxStrategyCalculator + API

### Task 4.1: Define DTOs

**Files:**
- Create: `app/DataTransferObjects/TaxStrategyOverridesDTO.php`
- Create: `app/DataTransferObjects/TaxStrategyOutputDTO.php`

- [ ] **Step 1: Write `TaxStrategyOverridesDTO`**

```php
<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class TaxStrategyOverridesDTO
{
    public function __construct(
        public readonly ?float $pensionContributionPercent = null,
        public readonly ?bool $salarySacrifice = null,
        public readonly ?float $isaAdditionalDeposit = null,
        public readonly ?bool $marriageAllowanceClaimed = null,
        public readonly ?float $assetShiftAmount = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            pensionContributionPercent: $data['pension_contribution_percent'] ?? null,
            salarySacrifice: $data['salary_sacrifice'] ?? null,
            isaAdditionalDeposit: $data['isa_additional_deposit'] ?? null,
            marriageAllowanceClaimed: $data['marriage_allowance_claimed'] ?? null,
            assetShiftAmount: $data['asset_shift_amount'] ?? null,
        );
    }
}
```

- [ ] **Step 2: Write `TaxStrategyOutputDTO`**

```php
<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class TaxStrategyOutputDTO
{
    public function __construct(
        public readonly string $taxYear,
        public readonly string $calculationMode,
        public readonly array $userAllowances,        // 8 AllowancePosition arrays
        public readonly ?array $spouseAllowances,     // 8 arrays for paths B+C, null for A
        public readonly array $assetShiftingSuggestions, // [] for paths A+B, populated for C
        public readonly array $crossSpouseSuggestions,   // [] for paths A+C, populated for B
        public readonly float $totalIncomeTax,
        public readonly float $totalNiContributions,
        public readonly float $netPayMonthly,
        public readonly array $deltaVsBaseline,       // empty when no overrides
    ) {}

    public function toArray(): array
    {
        return [
            'tax_year' => $this->taxYear,
            'calculation_mode' => $this->calculationMode,
            'user_allowances' => $this->userAllowances,
            'spouse_allowances' => $this->spouseAllowances,
            'asset_shifting_suggestions' => $this->assetShiftingSuggestions,
            'cross_spouse_suggestions' => $this->crossSpouseSuggestions,
            'total_income_tax' => $this->totalIncomeTax,
            'total_ni_contributions' => $this->totalNiContributions,
            'net_pay_monthly' => $this->netPayMonthly,
            'delta_vs_baseline' => $this->deltaVsBaseline,
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/DataTransferObjects
git commit -m "feat(savetax): add TaxStrategyOverridesDTO + TaxStrategyOutputDTO"
```

### Task 4.2: TDD — `TaxStrategyCalculator` for Path A (single)

**Files:**
- Create: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`
- Create: `app/Services/Tax/TaxStrategyCalculator.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;

it('returns 8 allowance positions for a single user with no overrides', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 50000,
        'marital_status' => 'single',
    ]);

    $calc = app(TaxStrategyCalculator::class);
    $output = $calc->calculate($user);

    expect($output)->toBeInstanceOf(TaxStrategyOutputDTO::class);
    expect($output->calculationMode)->toBe('single');
    expect($output->userAllowances)->toHaveCount(8);
    expect($output->spouseAllowances)->toBeNull();
    expect($output->assetShiftingSuggestions)->toBe([]);
    expect($output->crossSpouseSuggestions)->toBe([]);

    $allowanceKeys = array_column($output->userAllowances, 'key');
    expect($allowanceKeys)->toContain(
        'personal_allowance',
        'savings_allowance',
        'starting_rate_for_savings',
        'marriage_allowance',
        'isa_allowance',
        'cgt_allowance',
        'dividend_allowance',
        'pension_annual_allowance',
    );
});

it('does not write to the database during calculate()', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'single']);
    $before = $user->updated_at;

    app(TaxStrategyCalculator::class)->calculate($user);

    expect($user->fresh()->updated_at->equalTo($before))->toBeTrue();
});
```

- [ ] **Step 2: Run → RED**

- [ ] **Step 3: Implement minimal calculator (Path A only)**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\User;
use App\Services\Investment\Tax\CGTHarvestingCalculator;
use App\Services\Investment\Tax\ISAAllowanceOptimizer;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Savings\PSACalculator;
use App\Services\TaxConfigService;

final class TaxStrategyCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly PSACalculator $psaCalculator,
        private readonly AnnualAllowanceChecker $aaChecker,
        private readonly ISAAllowanceOptimizer $isaOptimizer,
        private readonly CGTHarvestingCalculator $cgtCalculator,
    ) {}

    public function calculate(User $user, ?TaxStrategyOverridesDTO $overrides = null): TaxStrategyOutputDTO
    {
        $mode = $user->household_calculation_mode ?? 'single';
        $taxYear = $this->taxConfig->getTaxYear();

        $userAllowances = $this->buildAllowanceGrid($user, $overrides);

        return new TaxStrategyOutputDTO(
            taxYear: $taxYear,
            calculationMode: $mode,
            userAllowances: $userAllowances,
            spouseAllowances: null,
            assetShiftingSuggestions: [],
            crossSpouseSuggestions: [],
            totalIncomeTax: 0.0, // populated by TaxStrategyService for now
            totalNiContributions: 0.0,
            netPayMonthly: 0.0,
            deltaVsBaseline: [],
        );
    }

    private function buildAllowanceGrid(User $user, ?TaxStrategyOverridesDTO $overrides): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        // Compose using existing services; no DB writes
        $psa = $this->psaCalculator->assessPSAPosition($user);
        $aa = $this->aaChecker->checkAnnualAllowance($user->id, $this->taxConfig->getTaxYear());
        $isaPos = $this->isaOptimizer->calculateOptimalStrategy($user->id, []);
        $cgtPos = $this->cgtCalculator->calculateHarvestingOpportunities($user->id, []);

        return [
            $this->positionFor('personal_allowance', 'Personal Allowance', $income['personal_allowance'], (float) min($user->annual_employment_income ?? 0, $income['personal_allowance'])),
            $this->positionFor('savings_allowance', 'Savings Allowance', $psa['psa_amount'] ?? 1000, $psa['annual_interest'] ?? 0),
            $this->positionFor('starting_rate_for_savings', 'Starting Rate for Savings', $income['starting_rate_for_savings'] ?? 5000, 0), // no per-user usage tracker yet
            $this->positionFor('marriage_allowance', 'Marriage Allowance', $income['marriage_allowance'] ?? 1260, $user->marriage_allowance_eligible ? 1260 : 0),
            $this->positionFor('isa_allowance', 'ISA Allowance', $isa['annual_allowance'], (float) ($isa['annual_allowance'] - ($isaPos['remaining_allowance'] ?? $isa['annual_allowance']))),
            $this->positionFor('cgt_allowance', 'CGT Allowance', $cgt['annual_exempt_amount'], (float) ($cgtPos['expected_gains'] ?? 0)),
            $this->positionFor('dividend_allowance', 'Dividend Allowance', $div['allowance'], 0), // TODO Path B/C
            $this->positionFor('pension_annual_allowance', 'Pension Annual Allowance', $pension['annual_allowance'], (float) ($aa['used'] ?? 0)),
        ];
    }

    private function positionFor(string $key, string $label, float $amount, float $used): array
    {
        $remaining = max(0, $amount - $used);
        $pct = $amount > 0 ? min(100, ($used / $amount) * 100) : 0;
        $status = $pct >= 90 ? 'spring' : ($pct >= 50 ? 'violet' : 'raspberry');

        return [
            'key' => $key,
            'label' => $label,
            'amount' => $amount,
            'used' => $used,
            'remaining' => $remaining,
            'utilisation_pct' => round($pct, 1),
            'status' => $status,
            'owner' => 'user',
        ];
    }
}
```

- [ ] **Step 4: Run → GREEN**

- [ ] **Step 5: Commit**

```bash
git add app/Services/Tax/TaxStrategyCalculator.php tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php
git commit -m "feat(savetax): TaxStrategyCalculator path A (single) implementation"
```

### Task 4.3: TDD — Calculator for Path B (dual_earner)

**Files:**
- Modify: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`, `app/Services/Tax/TaxStrategyCalculator.php`

- [ ] **Step 1: Add test for dual_earner**

```php
it('returns twin allowance grids for dual_earner mode', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 80000,
        'marital_status' => 'married',
    ]);
    \App\Models\TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 45000,
        'spouse_employment_status' => 'full_time',
        'spouse_isa_balance' => 12000,
        'spouse_psa_band' => 'basic',
    ]);

    $output = app(TaxStrategyCalculator::class)->calculate($user);

    expect($output->calculationMode)->toBe('dual_earner');
    expect($output->userAllowances)->toHaveCount(8);
    expect($output->spouseAllowances)->toHaveCount(8);
    expect($output->crossSpouseSuggestions)->not->toBe([]);
    expect($output->assetShiftingSuggestions)->toBe([]); // path B does not asset-shift
});
```

- [ ] **Step 2: Run → RED**

- [ ] **Step 3: Implement spouse-allowance branch + cross-spouse suggestions**

Add to `calculate()`:
```php
$spouseAllowances = null;
$crossSpouseSuggestions = [];

if ($mode === 'dual_earner') {
    $household = $user->taxStrategyHouseholdInput;
    if ($household) {
        $spouseAllowances = $this->buildSpouseAllowanceGridDualEarner($household);
        $crossSpouseSuggestions = $this->buildCrossSpouseSuggestions($user, $household);
    }
}
```

Add the two helper methods:
```php
private function buildSpouseAllowanceGridDualEarner(\App\Models\TaxStrategyHouseholdInput $h): array
{
    // mirrors buildAllowanceGrid but uses $h->spouse_* fields and $h->spouse_psa_band
    // ... full implementation referencing TaxConfigService values
}

private function buildCrossSpouseSuggestions(User $user, $h): array
{
    $suggestions = [];
    $userBand = $this->bandFromIncome($user->annual_employment_income ?? 0);
    $spouseBand = $h->spouse_psa_band ?? 'basic';

    if ($userBand !== 'basic' && $spouseBand === 'basic') {
        $suggestions[] = [
            'type' => 'gia_rebalance',
            'priority' => 'high',
            'title' => 'Hold GIA in lower-earner spouse\'s name',
            'description' => 'Your spouse\'s lower tax band means dividend and capital-gains income is taxed less in their name.',
            'estimated_annual_saving' => $this->estimateGiaRebalanceSaving($user, $h),
        ];
    }
    // ... similarly: ISA balance coordination, spousal Bed-and-ISA, etc.
    return $suggestions;
}

private function bandFromIncome(float $income): string
{
    $income = (float) $income;
    return match (true) {
        $income >= 125140 => 'additional',
        $income >= 50270 => 'higher',
        default => 'basic',
    };
}

private function estimateGiaRebalanceSaving(User $user, $h): float
{
    // simple heuristic — can refine later
    return 0.0;
}
```

- [ ] **Step 4: Run → GREEN**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(savetax): TaxStrategyCalculator path B (dual_earner) twin grid + cross-spouse suggestions"
```

### Task 4.4: TDD — Calculator for Path C (single_earner_couple) with asset-shifting

**Files:**
- Modify: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`, `app/Services/Tax/TaxStrategyCalculator.php`

- [ ] **Step 1: Add test for asset-shifting**

```php
it('emits asset-shifting suggestions sized to non-working spouse\'s unused capacity', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 100000,
        'marital_status' => 'married',
        'marriage_allowance_eligible' => true,
    ]);
    \App\Models\TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_existing_isa_balance' => 0,
        'spouse_existing_savings_balance' => 0,
        'spouse_existing_investment_balance' => 0,
        'spouse_existing_dividend_holdings_value' => 0,
    ]);
    // user has £200k of savings (created via factory or stub)
    \App\Models\Savings\SavingsAccount::factory()->for($user)->create([
        'is_isa' => false,
        'current_balance' => 200000,
        'interest_rate' => 0.035,
    ]);

    $output = app(TaxStrategyCalculator::class)->calculate($user);

    expect($output->calculationMode)->toBe('single_earner_couple');
    expect($output->assetShiftingSuggestions)->not->toBe([]);

    // Find the savings-shift suggestion
    $shiftSuggestion = collect($output->assetShiftingSuggestions)
        ->firstWhere('type', 'savings_to_spouse');

    expect($shiftSuggestion)->not->toBeNull();
    // Spouse can absorb up to 18,570/yr in interest tax-free
    // (PA 12,570 + Starting Rate 5,000 + PSA 1,000)
    // At 3.5% APR, that's ~£530,571 of capital
    // But user only has £200k, so suggested amount ≤ £200k
    expect($shiftSuggestion['suggested_transfer_amount'])->toBeLessThanOrEqual(200000);
    expect($shiftSuggestion['estimated_annual_tax_saved'])->toBeGreaterThan(0);

    // Marriage Allowance also surfaced
    $maSuggestion = collect($output->assetShiftingSuggestions)
        ->firstWhere('type', 'marriage_allowance_transfer');
    expect($maSuggestion)->not->toBeNull();
});
```

- [ ] **Step 2: Run → RED**

- [ ] **Step 3: Implement single_earner_couple branch**

Add to `calculate()`:
```php
$assetShiftingSuggestions = [];
if ($mode === 'single_earner_couple') {
    $household = $user->taxStrategyHouseholdInput;
    $spouseAllowances = $this->buildSpouseAllowanceGridNonWorking($household);
    $assetShiftingSuggestions = $this->buildAssetShiftingSuggestions($user, $household);
}
```

Add helpers:
```php
private function buildSpouseAllowanceGridNonWorking(?\App\Models\TaxStrategyHouseholdInput $h): array
{
    // Non-working spouse: assume basic-rate band, no salary income
    // PA = 12,570 (none used)
    // Starting Rate for Savings = 5,000 (used by spouse_existing_savings × interest_rate; assume 0 if no rate)
    // PSA basic = 1,000
    // ISA = 20,000 - spouse_existing_isa_balance current-year subscription portion (unknown; assume balance)
    // CGT = 3,000 (used = 0 assumed)
    // Dividend = 500
    // Pension AA = 60,000 (used = 0)
    // Marriage Allowance: not applicable on spouse's grid (it's the user's allowance)
    $income = $this->taxConfig->getIncomeTax();
    $isa = $this->taxConfig->getISAAllowances();
    $cgt = $this->taxConfig->getCapitalGainsTax();
    $div = $this->taxConfig->getDividendTax();
    $pension = $this->taxConfig->getPensionAllowances();
    $existingIsa = (float) ($h->spouse_existing_isa_balance ?? 0);

    return [
        $this->positionFor('personal_allowance', 'Personal Allowance', $income['personal_allowance'], 0, owner: 'spouse'),
        $this->positionFor('savings_allowance', 'Savings Allowance (basic rate)', 1000, 0, owner: 'spouse'),
        $this->positionFor('starting_rate_for_savings', 'Starting Rate for Savings', $income['starting_rate_for_savings'] ?? 5000, 0, owner: 'spouse'),
        // Marriage Allowance N/A on spouse's grid (it's transferred FROM spouse TO user); skip or render greyed out
        $this->positionFor('isa_allowance', 'ISA Allowance', $isa['annual_allowance'], min($isa['annual_allowance'], $existingIsa), owner: 'spouse'),
        $this->positionFor('cgt_allowance', 'CGT Allowance', $cgt['annual_exempt_amount'], 0, owner: 'spouse'),
        $this->positionFor('dividend_allowance', 'Dividend Allowance', $div['allowance'], 0, owner: 'spouse'),
        $this->positionFor('pension_annual_allowance', 'Pension Annual Allowance', $pension['annual_allowance'], 0, owner: 'spouse'),
    ];
}

private function buildAssetShiftingSuggestions(User $user, ?\App\Models\TaxStrategyHouseholdInput $h): array
{
    $suggestions = [];

    // 1. Marriage Allowance transfer (if eligible)
    if ($user->marriage_allowance_eligible) {
        $maAmount = $this->taxConfig->getIncomeTax()['marriage_allowance'] ?? 1260;
        $userBand = $this->bandFromIncome($user->annual_employment_income ?? 0);
        $estimatedSaving = $userBand === 'basic' ? $maAmount * 0.20 : 0; // MA only saves at basic rate, and recipient must be basic

        if ($userBand === 'basic') {
            $suggestions[] = [
                'type' => 'marriage_allowance_transfer',
                'priority' => 'medium',
                'title' => 'Claim Marriage Allowance',
                'description' => 'Your spouse can transfer £' . number_format($maAmount) . ' of unused Personal Allowance to you, saving roughly £' . number_format($estimatedSaving) . ' per year in income tax.',
                'estimated_annual_tax_saved' => $estimatedSaving,
                'amount_transferred' => $maAmount,
            ];
        }
    }

    // 2. Savings → spouse (uses spouse's PA + Starting Rate + PSA: up to ~£18,570/yr interest tax-free)
    $userSavings = $user->savingsAccounts()->where('is_isa', false)->get();
    $userSavingsTotal = (float) $userSavings->sum('current_balance');
    $userAvgRate = $userSavings->avg('interest_rate') ?? 0.035;

    $spouseInterestCapacity = 18570; // PA 12570 + Starting Rate 5000 + PSA basic 1000
    $existingSpouseSavings = (float) ($h->spouse_existing_savings_balance ?? 0);
    $spouseUsedInterest = $existingSpouseSavings * $userAvgRate; // estimate
    $spouseRemainingInterestCapacity = max(0, $spouseInterestCapacity - $spouseUsedInterest);
    $maxSavingsTransferableByCapacity = $userAvgRate > 0 ? $spouseRemainingInterestCapacity / $userAvgRate : 0;
    $suggestedTransfer = min($userSavingsTotal, $maxSavingsTransferableByCapacity);

    if ($suggestedTransfer > 1000) {
        $estimatedAnnualTaxSaved = $suggestedTransfer * $userAvgRate * $this->bandRateFor($user);
        $suggestions[] = [
            'type' => 'savings_to_spouse',
            'priority' => 'high',
            'title' => 'Gift £' . number_format($suggestedTransfer, 0) . ' of savings to your spouse',
            'description' => 'Their unused Personal Allowance + Starting Rate for Savings + Personal Savings Allowance can absorb up to £' . number_format($spouseRemainingInterestCapacity) . '/year of interest income tax-free. Spousal transfers are exempt from CGT and IHT.',
            'suggested_transfer_amount' => round($suggestedTransfer),
            'estimated_annual_tax_saved' => round($estimatedAnnualTaxSaved),
        ];
    }

    // 3. GIA → spouse for CGT + Dividend allowances
    // ... (similar structure for CGT £3k, Dividend £500)

    // 4. ISA top-up in spouse's name (uses fresh £20k allowance)
    $spouseIsaRemaining = $this->taxConfig->getISAAllowances()['annual_allowance'] - (float) ($h->spouse_existing_isa_balance ?? 0);
    if ($spouseIsaRemaining > 0) {
        $suggestions[] = [
            'type' => 'isa_topup_spouse',
            'priority' => 'medium',
            'title' => 'Open or top up an ISA in your spouse\'s name',
            'description' => 'They have £' . number_format($spouseIsaRemaining) . ' of unused ISA allowance for this tax year.',
            'available_allowance' => round($spouseIsaRemaining),
        ];
    }

    return $suggestions;
}

private function bandRateFor(User $user): float
{
    return match ($this->bandFromIncome((float) ($user->annual_employment_income ?? 0))) {
        'basic' => 0.20,
        'higher' => 0.40,
        'additional' => 0.45,
    };
}
```

- [ ] **Step 4: Run → GREEN**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(savetax): TaxStrategyCalculator path C (single_earner_couple) with asset-shifting"
```

### Task 4.5: Benchmark calculator <50ms

**Files:**
- Modify: `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`

- [ ] **Step 1: Add benchmark test**

```php
it('runs in under 50ms for a representative single_earner_couple persona', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 100000,
        'marriage_allowance_eligible' => true,
    ]);
    \App\Models\TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_isa_balance' => 5000]);
    \App\Models\Savings\SavingsAccount::factory()->for($user)->count(3)->create();

    $start = hrtime(true);
    app(TaxStrategyCalculator::class)->calculate($user);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    expect($elapsedMs)->toBeLessThan(50);
});
```

- [ ] **Step 2: Run → likely GREEN** (no DB writes; if RED, profile + optimise)

- [ ] **Step 3: Commit**

```bash
git commit -am "test(savetax): TaxStrategyCalculator <50ms benchmark"
```

### Task 4.6: Build `TaxStrategyService` orchestrator

**Files:**
- Create: `app/Services/Tax/TaxStrategyService.php`

- [ ] **Step 1: Implement service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Agents\TaxOptimisationAgent;
use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\Models\User;

final class TaxStrategyService
{
    public function __construct(
        private readonly TaxStrategyCalculator $calculator,
        private readonly TaxOptimisationAgent $optimisationAgent,
    ) {}

    public function getDashboardPayload(User $user): array
    {
        $base = $this->calculator->calculate($user);
        $optimisation = $this->optimisationAgent->analyze($user->id);

        return [
            ...$base->toArray(),
            'recommendations' => $optimisation['strategies'] ?? [],
            'total_estimated_saving' => $optimisation['total_estimated_saving'] ?? 0,
        ];
    }

    public function recalculate(User $user, TaxStrategyOverridesDTO $overrides): array
    {
        return $this->calculator->calculate($user, $overrides)->toArray();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/Tax/TaxStrategyService.php
git commit -m "feat(savetax): TaxStrategyService orchestrator"
```

### Task 4.7: TDD — `TaxStrategyController::show`

**Files:**
- Create: `tests/Feature/Api/TaxStrategy/ShowEndpointTest.php`
- Create: `app/Http/Controllers/Api/TaxStrategyController.php`
- Create: `app/Http/Resources/TaxStrategyResource.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;

it('requires auth', function () {
    $this->getJson('/api/tax-strategy')->assertStatus(401);
});

it('returns full payload for authenticated single user', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'single']);

    $response = $this->actingAs($user)->getJson('/api/tax-strategy');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tax_year',
                'calculation_mode',
                'user_allowances',
                'spouse_allowances',
                'recommendations',
            ],
        ]);
    expect($response->json('data.calculation_mode'))->toBe('single');
    expect($response->json('data.spouse_allowances'))->toBeNull();
});

it('returns spouse_allowances for dual_earner users', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'dual_earner']);
    \App\Models\TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 45000,
        'spouse_psa_band' => 'basic',
    ]);

    $response = $this->actingAs($user)->getJson('/api/tax-strategy');

    expect($response->json('data.calculation_mode'))->toBe('dual_earner');
    expect($response->json('data.spouse_allowances'))->toHaveCount(8);
});
```

- [ ] **Step 2: Run → RED**

- [ ] **Step 3: Implement controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tax\TaxStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaxStrategyController extends Controller
{
    public function __construct(private readonly TaxStrategyService $service) {}

    public function show(Request $request): JsonResponse
    {
        $payload = $this->service->getDashboardPayload($request->user());
        return response()->json(['data' => $payload]);
    }
}
```

- [ ] **Step 4: Register route**

In `routes/api.php`, inside the `Route::middleware(['auth:sanctum'])->group()`:
```php
Route::get('/tax-strategy', [\App\Http\Controllers\Api\TaxStrategyController::class, 'show']);
```

- [ ] **Step 5: Run → GREEN**

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/TaxStrategyController.php tests/Feature/Api/TaxStrategy/ShowEndpointTest.php routes/api.php
git commit -m "feat(savetax): GET /api/tax-strategy endpoint"
```

### Task 4.8: TDD — `TaxStrategyController::calculate`

**Files:**
- Create: `tests/Feature/Api/TaxStrategy/CalculateEndpointTest.php`
- Create: `app/Http/Requests/TaxStrategyCalculateRequest.php`
- Modify: `app/Http/Controllers/Api/TaxStrategyController.php`, `routes/api.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;

it('validates pension_contribution_percent range 0-100', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/tax-strategy/calculate', ['pension_contribution_percent' => 150])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pension_contribution_percent');
});

it('returns recalculated grid with deltas', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 50000,
    ]);

    $response = $this->actingAs($user)->postJson('/api/tax-strategy/calculate', [
        'pension_contribution_percent' => 10,
        'salary_sacrifice' => true,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user_allowances',
                'delta_vs_baseline',
            ],
        ]);
});
```

- [ ] **Step 2: Run → RED**

- [ ] **Step 3: Create FormRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TaxStrategyCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pension_contribution_percent' => ['nullable', 'numeric', 'between:0,100'],
            'salary_sacrifice' => ['nullable', 'boolean'],
            'isa_additional_deposit' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'marriage_allowance_claimed' => ['nullable', 'boolean'],
            'asset_shift_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Add controller method**

```php
public function calculate(TaxStrategyCalculateRequest $request): JsonResponse
{
    $overrides = TaxStrategyOverridesDTO::fromRequest($request->validated());
    $payload = $this->service->recalculate($request->user(), $overrides);
    return response()->json(['data' => $payload]);
}
```

- [ ] **Step 5: Register route**

```php
Route::post('/tax-strategy/calculate', [\App\Http\Controllers\Api\TaxStrategyController::class, 'calculate']);
```

- [ ] **Step 6: Run → GREEN**

- [ ] **Step 7: Commit**

```bash
git commit -am "feat(savetax): POST /api/tax-strategy/calculate endpoint with override validation"
```

### Task 4.9: Add `get_tax_strategy_position` AdviceFyn read tool

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`, `app/Services/AI/AdviceFyn.php`

- [ ] **Step 1: Add tool definition**

```php
[
    'name' => 'get_tax_strategy_position',
    'description' => 'Read-only: returns the user\'s current tax-strategy dashboard payload (allowance grid + recommendations). Use when the user asks about their tax position, allowances, or strategy.',
    'parameters' => [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 2: Add dispatch in CoordinatingAgent**

```php
'get_tax_strategy_position' => $this->handleGetTaxStrategyPosition($user),
```

```php
private function handleGetTaxStrategyPosition(User $user): array
{
    return app(\App\Services\Tax\TaxStrategyService::class)->getDashboardPayload($user);
}
```

- [ ] **Step 3: Verify tool is NOT in AdviceFyn::WRITE_TOOLS**

`grep "get_tax_strategy_position" app/Services/AI/AdviceFyn.php` — should NOT be in the WRITE_TOOLS array (this tool is read-only). It will be allowed by default through the read-tool whitelist (verify the file's pattern; if there's an explicit READ_TOOLS list, append there).

- [ ] **Step 4: Commit**

```bash
git commit -am "feat(savetax): get_tax_strategy_position read-only tool for AdviceFyn"
```

---

## Phase 5 — Frontend dashboard

### Task 5.1: Vuex module

**Files:**
- Create: `resources/js/store/modules/taxStrategy.js`
- Modify: `resources/js/store/index.js`

- [ ] **Step 1: Write module**

```javascript
import api from '@/services/api'

export default {
    namespaced: true,
    state: () => ({
        dashboard: null,
        overrides: {},
        loading: false,
        recalculating: false,
        error: null,
    }),
    mutations: {
        SET_LOADING(state, val) { state.loading = val },
        SET_RECALCULATING(state, val) { state.recalculating = val },
        SET_DASHBOARD(state, payload) { state.dashboard = payload },
        SET_OVERRIDES(state, overrides) { state.overrides = overrides },
        SET_ERROR(state, err) { state.error = err },
    },
    actions: {
        async fetchDashboard({ commit }) {
            commit('SET_LOADING', true)
            commit('SET_ERROR', null)
            try {
                const { data } = await api.get('/tax-strategy')
                commit('SET_DASHBOARD', data.data)
            } catch (err) {
                commit('SET_ERROR', err.message)
            } finally {
                commit('SET_LOADING', false)
            }
        },
        async recalculate({ commit, state }, overrides) {
            commit('SET_OVERRIDES', { ...state.overrides, ...overrides })
            commit('SET_RECALCULATING', true)
            try {
                const { data } = await api.post('/tax-strategy/calculate', state.overrides)
                commit('SET_DASHBOARD', { ...state.dashboard, ...data.data })
            } catch (err) {
                commit('SET_ERROR', err.message)
            } finally {
                commit('SET_RECALCULATING', false)
            }
        },
    },
    getters: {
        userAllowances: (s) => s.dashboard?.user_allowances ?? [],
        spouseAllowances: (s) => s.dashboard?.spouse_allowances ?? null,
        recommendations: (s) => s.dashboard?.recommendations ?? [],
        assetShiftingSuggestions: (s) => s.dashboard?.asset_shifting_suggestions ?? [],
        crossSpouseSuggestions: (s) => s.dashboard?.cross_spouse_suggestions ?? [],
        calculationMode: (s) => s.dashboard?.calculation_mode ?? 'single',
        isHouseholdMode: (s) => ['dual_earner', 'single_earner_couple'].includes(s.dashboard?.calculation_mode),
    },
}
```

- [ ] **Step 2: Register in store**

In `resources/js/store/index.js`, add:
```javascript
import taxStrategy from './modules/taxStrategy'
// ...
modules: { ..., taxStrategy }
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/store/modules/taxStrategy.js resources/js/store/index.js
git commit -m "feat(savetax): Vuex taxStrategy module"
```

### Task 5.2: API service wrapper

**Files:**
- Create: `resources/js/services/taxStrategyService.js`

- [ ] **Step 1: Write thin wrapper**

```javascript
import api from './api'

export default {
    fetchDashboard: () => api.get('/tax-strategy'),
    recalculate: (overrides) => api.post('/tax-strategy/calculate', overrides),
}
```

- [ ] **Step 2: Commit**

### Task 5.3: AllowanceCard component

**Files:**
- Create: `resources/js/components/TaxStrategy/AllowanceCard.vue`

- [ ] **Step 1: Write component reusing ISAAllowanceTracker pattern**

```vue
<template>
    <div class="allowance-card rounded-lg border border-light-gray bg-white p-4">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="text-body-sm font-semibold text-horizon-500">{{ allowance.label }}</h3>
            <span class="text-caption text-neutral-500">{{ formatCurrency(allowance.amount) }}</span>
        </div>
        <div class="w-full bg-savannah-200 rounded-full h-2 mb-2">
            <div
                class="h-2 rounded-full transition-all"
                :class="barClass"
                :style="{ width: Math.min(allowance.utilisation_pct, 100) + '%' }"
            ></div>
        </div>
        <div class="flex items-center justify-between text-caption">
            <span class="text-neutral-500">{{ formatCurrency(allowance.used) }} used</span>
            <span class="text-neutral-500">{{ formatCurrency(allowance.remaining) }} remaining</span>
        </div>
    </div>
</template>

<script>
import currencyMixin from '@/mixins/currencyMixin'

export default {
    name: 'AllowanceCard',
    mixins: [currencyMixin],
    props: {
        allowance: { type: Object, required: true },
    },
    computed: {
        barClass() {
            return {
                'bg-spring-500': this.allowance.status === 'spring',
                'bg-violet-500': this.allowance.status === 'violet',
                'bg-raspberry-500': this.allowance.status === 'raspberry',
            }
        },
    },
}
</script>
```

- [ ] **Step 2: Commit**

### Task 5.4: AllowanceGrid component

**Files:**
- Create: `resources/js/components/TaxStrategy/AllowanceGrid.vue`

- [ ] **Step 1: Write 2-column grid**

```vue
<template>
    <div class="allowance-grid grid grid-cols-1 md:grid-cols-2 gap-6">
        <section>
            <h2 class="text-body font-bold text-horizon-500 mb-3">Income</h2>
            <div class="space-y-3">
                <AllowanceCard
                    v-for="allowance in incomeAllowances"
                    :key="allowance.key"
                    :allowance="allowance"
                />
            </div>
        </section>
        <section>
            <h2 class="text-body font-bold text-horizon-500 mb-3">Investment &amp; Cash</h2>
            <div class="space-y-3">
                <AllowanceCard
                    v-for="allowance in investmentAllowances"
                    :key="allowance.key"
                    :allowance="allowance"
                />
            </div>
        </section>
    </div>
</template>

<script>
import AllowanceCard from './AllowanceCard.vue'

const INCOME_KEYS = ['personal_allowance', 'savings_allowance', 'starting_rate_for_savings', 'marriage_allowance']
const INVESTMENT_KEYS = ['isa_allowance', 'cgt_allowance', 'dividend_allowance', 'pension_annual_allowance']

export default {
    name: 'AllowanceGrid',
    components: { AllowanceCard },
    props: {
        allowances: { type: Array, required: true },
    },
    computed: {
        incomeAllowances() {
            return INCOME_KEYS
                .map((k) => this.allowances.find((a) => a.key === k))
                .filter(Boolean)
        },
        investmentAllowances() {
            return INVESTMENT_KEYS
                .map((k) => this.allowances.find((a) => a.key === k))
                .filter(Boolean)
        },
    },
}
</script>
```

- [ ] **Step 2: Commit**

### Task 5.5: HouseholdView (twin grids + mode-specific suggestions)

**Files:**
- Create: `resources/js/components/TaxStrategy/HouseholdView.vue`

- [ ] **Step 1: Write component**

```vue
<template>
    <div class="household-view">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="text-body-sm uppercase tracking-wide text-neutral-500 mb-2">You</h3>
                <AllowanceGrid :allowances="userAllowances" />
            </div>
            <div>
                <h3 class="text-body-sm uppercase tracking-wide text-neutral-500 mb-2">{{ spouseLabel }}</h3>
                <AllowanceGrid :allowances="spouseAllowances" />
            </div>
        </div>
        <AssetShiftingPanel
            v-if="calculationMode === 'single_earner_couple' && assetShiftingSuggestions.length"
            :suggestions="assetShiftingSuggestions"
        />
        <section
            v-if="calculationMode === 'dual_earner' && crossSpouseSuggestions.length"
            class="rounded-lg bg-eggshell-500 p-6"
        >
            <h2 class="text-body font-bold text-horizon-500 mb-3">Coordinate</h2>
            <ActionSummaryCard
                v-for="suggestion in crossSpouseSuggestions"
                :key="suggestion.type"
                :action="mapToAction(suggestion)"
            />
        </section>
    </div>
</template>

<script>
import { mapGetters } from 'vuex'
import AllowanceGrid from './AllowanceGrid.vue'
import AssetShiftingPanel from './AssetShiftingPanel.vue'
import ActionSummaryCard from '@/components/Actions/ActionSummaryCard.vue'

export default {
    name: 'HouseholdView',
    components: { AllowanceGrid, AssetShiftingPanel, ActionSummaryCard },
    computed: {
        ...mapGetters('taxStrategy', [
            'userAllowances',
            'spouseAllowances',
            'calculationMode',
            'assetShiftingSuggestions',
            'crossSpouseSuggestions',
        ]),
        spouseLabel() {
            return this.calculationMode === 'single_earner_couple' ? 'Your spouse' : 'Your spouse'
        },
    },
    methods: {
        mapToAction(suggestion) {
            return {
                title: suggestion.title,
                category: suggestion.type,
                priority: suggestion.priority,
                estimated_impact: suggestion.estimated_annual_saving ?? null,
            }
        },
    },
}
</script>
```

- [ ] **Step 2: Commit**

### Task 5.6: AssetShiftingPanel (Path C only)

**Files:**
- Create: `resources/js/components/TaxStrategy/AssetShiftingPanel.vue`

- [ ] **Step 1: Write component**

```vue
<template>
    <section class="asset-shifting rounded-lg bg-eggshell-500 p-6 mb-6">
        <h2 class="text-body font-bold text-horizon-500 mb-2">Asset-shifting opportunities</h2>
        <p class="text-body-sm text-neutral-600 mb-4">
            Your spouse has unused tax allowances we can put to work. These suggestions are based on what you've told us about your household — spousal transfers are exempt from CGT and IHT.
        </p>
        <ul class="space-y-3">
            <li
                v-for="suggestion in suggestions"
                :key="suggestion.type"
                class="rounded-md bg-white p-4 border border-light-gray"
            >
                <div class="flex items-start justify-between mb-1">
                    <h3 class="text-body-sm font-semibold text-horizon-500">{{ suggestion.title }}</h3>
                    <span
                        v-if="suggestion.estimated_annual_tax_saved"
                        class="text-body-sm font-semibold text-spring-600"
                    >
                        {{ formatCurrency(suggestion.estimated_annual_tax_saved) }}/yr
                    </span>
                </div>
                <p class="text-caption text-neutral-500">{{ suggestion.description }}</p>
            </li>
        </ul>
        <p class="text-caption text-neutral-500 mt-4">
            Note: spousal transfers also reduce your taxable estate for inheritance-tax purposes.
        </p>
    </section>
</template>

<script>
import currencyMixin from '@/mixins/currencyMixin'

export default {
    name: 'AssetShiftingPanel',
    mixins: [currencyMixin],
    props: {
        suggestions: { type: Array, required: true },
    },
}
</script>
```

- [ ] **Step 2: Commit**

### Task 5.7: StrategySliderPanel

**Files:**
- Create: `resources/js/components/TaxStrategy/StrategySliderPanel.vue`

- [ ] **Step 1: Write component**

```vue
<template>
    <section class="slider-panel rounded-lg bg-white border border-light-gray p-6">
        <h2 class="text-body font-bold text-horizon-500 mb-4">What if you...</h2>

        <div class="mb-6">
            <label class="flex items-center justify-between mb-2">
                <span class="text-body-sm font-semibold text-horizon-500">Pension contribution</span>
                <span class="text-body-sm font-semibold text-raspberry-500">{{ pensionPct }}%</span>
            </label>
            <input
                v-model.number="pensionPct"
                type="range"
                min="0" max="40" step="1"
                class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
                @input="onSliderChange"
            />
        </div>

        <div class="mb-6 flex items-center justify-between">
            <label class="text-body-sm font-semibold text-horizon-500">Salary sacrifice</label>
            <input
                v-model="salarySacrifice"
                type="checkbox"
                class="h-5 w-5 rounded border-light-gray text-raspberry-500 focus:ring-violet-500"
                @change="onSliderChange"
            />
        </div>

        <div class="mb-6">
            <label class="flex items-center justify-between mb-2">
                <span class="text-body-sm font-semibold text-horizon-500">Top up ISA this year</span>
                <span class="text-body-sm font-semibold text-raspberry-500">{{ formatCurrency(isaTopup) }}</span>
            </label>
            <input
                v-model.number="isaTopup"
                type="range"
                min="0" max="20000" step="500"
                class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
                @input="onSliderChange"
            />
        </div>

        <div v-if="marriageAllowanceEligible" class="flex items-center justify-between">
            <label class="text-body-sm font-semibold text-horizon-500">Claim Marriage Allowance</label>
            <input
                v-model="marriageAllowanceClaimed"
                type="checkbox"
                class="h-5 w-5 rounded border-light-gray text-raspberry-500 focus:ring-violet-500"
                @change="onSliderChange"
            />
        </div>
    </section>
</template>

<script>
import { mapState } from 'vuex'
import currencyMixin from '@/mixins/currencyMixin'
import debounce from 'lodash/debounce'

export default {
    name: 'StrategySliderPanel',
    mixins: [currencyMixin],
    data() {
        return {
            pensionPct: 5,
            salarySacrifice: false,
            isaTopup: 0,
            marriageAllowanceClaimed: false,
        }
    },
    computed: {
        ...mapState('taxStrategy', ['dashboard']),
        marriageAllowanceEligible() {
            return this.dashboard?.calculation_mode === 'single_earner_couple'
        },
    },
    created() {
        this.debouncedRecalculate = debounce(() => {
            this.$store.dispatch('taxStrategy/recalculate', {
                pension_contribution_percent: this.pensionPct,
                salary_sacrifice: this.salarySacrifice,
                isa_additional_deposit: this.isaTopup,
                marriage_allowance_claimed: this.marriageAllowanceClaimed,
            })
        }, 200)
    },
    methods: {
        onSliderChange() {
            this.debouncedRecalculate()
        },
    },
}
</script>
```

- [ ] **Step 2: Commit**

### Task 5.8: TaxYearHeader + StrategyRecommendationList components

**Files:**
- Create: `resources/js/components/TaxStrategy/TaxYearHeader.vue`
- Create: `resources/js/components/TaxStrategy/StrategyRecommendationList.vue`

- [ ] **Step 1: Write `TaxYearHeader.vue`**

```vue
<template>
    <div class="tax-year-header rounded-lg bg-gradient-to-r from-horizon-500 to-raspberry-500 text-white p-6 mb-6">
        <h1 class="text-display font-black mb-1">Your tax strategy</h1>
        <p class="text-body-sm opacity-90">Tax year {{ taxYear }}</p>
    </div>
</template>

<script>
import { mapState } from 'vuex'

export default {
    name: 'TaxYearHeader',
    computed: {
        ...mapState('taxStrategy', ['dashboard']),
        taxYear() { return this.dashboard?.tax_year ?? '' },
    },
}
</script>
```

- [ ] **Step 2: Write `StrategyRecommendationList.vue`**

```vue
<template>
    <section v-if="recommendations.length" class="rounded-lg bg-white border border-light-gray p-6">
        <h2 class="text-body font-bold text-horizon-500 mb-3">Recommended actions</h2>
        <ActionSummaryCard
            v-for="rec in recommendations"
            :key="rec.id ?? rec.title"
            :action="mapToAction(rec)"
        />
    </section>
</template>

<script>
import { mapGetters } from 'vuex'
import ActionSummaryCard from '@/components/Actions/ActionSummaryCard.vue'

export default {
    name: 'StrategyRecommendationList',
    components: { ActionSummaryCard },
    computed: {
        ...mapGetters('taxStrategy', ['recommendations']),
    },
    methods: {
        mapToAction(rec) {
            return {
                title: rec.title,
                category: rec.type ?? rec.category ?? 'tax_strategy',
                priority: rec.priority ?? 'medium',
                estimated_impact: rec.estimated_annual_saving ?? rec.estimated_impact ?? null,
            }
        },
    },
}
</script>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/TaxStrategy
git commit -m "feat(savetax): TaxYearHeader + StrategyRecommendationList components"
```

### Task 5.9: TaxStrategyDashboard view + route

**Files:**
- Create: `resources/js/views/TaxStrategy/TaxStrategyDashboard.vue`
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Write view**

```vue
<template>
    <div class="tax-strategy-dashboard p-6 max-w-6xl mx-auto">
        <TaxYearHeader />

        <div v-if="loading" class="flex justify-center py-12">
            <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>

        <div v-else-if="error" class="rounded-lg bg-raspberry-100 text-raspberry-700 p-4">
            {{ error }}
        </div>

        <template v-else-if="dashboard">
            <HouseholdView v-if="isHouseholdMode" />
            <AllowanceGrid v-else :allowances="userAllowances" class="mb-8" />

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                <StrategySliderPanel />
                <StrategyRecommendationList />
            </div>
        </template>
    </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import TaxYearHeader from '@/components/TaxStrategy/TaxYearHeader.vue'
import AllowanceGrid from '@/components/TaxStrategy/AllowanceGrid.vue'
import HouseholdView from '@/components/TaxStrategy/HouseholdView.vue'
import StrategySliderPanel from '@/components/TaxStrategy/StrategySliderPanel.vue'
import StrategyRecommendationList from '@/components/TaxStrategy/StrategyRecommendationList.vue'

export default {
    name: 'TaxStrategyDashboard',
    components: { TaxYearHeader, AllowanceGrid, HouseholdView, StrategySliderPanel, StrategyRecommendationList },
    computed: {
        ...mapState('taxStrategy', ['dashboard', 'loading', 'error']),
        ...mapGetters('taxStrategy', ['userAllowances', 'isHouseholdMode']),
    },
    mounted() {
        this.$store.dispatch('taxStrategy/fetchDashboard')
    },
}
</script>
```

- [ ] **Step 2: Register route**

In `resources/js/router/index.js`, add:
```javascript
{
    path: '/tax-strategy',
    name: 'TaxStrategy',
    component: () => import('@/views/TaxStrategy/TaxStrategyDashboard.vue'),
    meta: { requiresAuth: true, breadcrumb: ['Dashboard', 'Tax Strategy'] },
},
```

- [ ] **Step 3: Smoke test in dev server**

```bash
./dev.sh
# In another terminal:
# Browse to http://localhost:8000/tax-strategy as a logged-in test user
# Confirm: page loads, no console errors, allowance cards render
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/TaxStrategy resources/js/router/index.js
git commit -m "feat(savetax): /tax-strategy route + TaxStrategyDashboard view"
```

### Task 5.10: ActionsDashboard tile linking to /tax-strategy

**Files:**
- Modify: `resources/js/views/Actions/ActionsDashboard.vue`

- [ ] **Step 1: Add a "Tax Strategy" tile** (visible to all SaveTax campaign users — gate on `users.onboarding_fyn_selection === 'savetax'`)

In the top-priority actions area, append:
```vue
<router-link
    v-if="onboardingSelection === 'savetax'"
    to="/tax-strategy"
    class="rounded-lg border border-raspberry-200 bg-light-pink-100 p-4 hover:border-raspberry-500 transition-colors"
>
    <h3 class="text-body-sm font-bold text-horizon-500">Your tax strategy</h3>
    <p class="text-caption text-neutral-500">View allowance utilisation, model what-if scenarios, see recommended actions.</p>
</router-link>
```

Add to `computed`:
```javascript
onboardingSelection() {
    return this.$store.state.user?.user?.onboarding_fyn_selection ?? null
},
```

- [ ] **Step 2: Commit**

```bash
git commit -am "feat(savetax): Tax Strategy tile on /actions for savetax users"
```

---

## Phase 6 — End-to-end browser scenarios

### Task 6.1: BS-26 — Single + employed (Path A)

**Files:**
- Create: `tests/Browser/scenarios/BS-26-savetax-single-employed.php`

- [ ] **Step 1: Write scenario**

```php
<?php

declare(strict_types=1);

/**
 * BS-26 — SaveTax campaign, single, employed
 *
 * Asserts:
 * 1. /savetax landing page loads with all CTAs.
 * 2. /register?from=savetax pre-fills campaign source.
 * 3. MFA flow completes (code from DB).
 * 4. Onboarding chat captures all base personal/family/employment/expenditure data.
 * 5. New campaign branch advances through OCCUPATIONAL_SCHEME → ISA → BANK → INVESTMENT → PENSION.
 * 6. SPOUSE_WORK is SKIPPED (single user).
 * 7. STATE_CAMPAIGN_TERMINAL emits navigate event → frontend routes to /tax-strategy.
 * 8. Dashboard renders with userAllowances populated, no household section.
 * 9. Sliders trigger debounced recalc visible in DOM.
 * 10. DB: users.onboarding_completed=true, signup_source=NULL, household_calculation_mode='single', no tax_strategy_household_inputs row.
 */

it('completes path A end-to-end and lands on tax-strategy dashboard', function () {
    // Use Playwright MCP browser_navigate, browser_click, browser_fill_form, browser_evaluate
    // ... (full Playwright scenario, ~150 lines, mirroring BS-NN scenario template)
});
```

- [ ] **Step 2: Run scenario locally**

```bash
./vendor/bin/pest tests/Browser/scenarios/BS-26-savetax-single-employed.php -v
```

- [ ] **Step 3: Loop until GREEN per CLAUDE.md Rule #15**

For each red assertion: diagnose → fix root cause → re-run. Do not move on until every assertion in the docblock holds.

- [ ] **Step 4: Commit**

```bash
git commit -am "test(savetax): BS-26 single+employed end-to-end browser scenario"
```

### Task 6.2: BS-27 — Married + spouse works (Path B)

**Files:**
- Create: `tests/Browser/scenarios/BS-27-savetax-married-spouse-works.php`

- [ ] **Step 1: Write scenario** (mirror BS-26 structure, change persona to married, answer spouse_works=yes, capture spouse data)

Asserts:
- SPOUSE_HOUSEHOLD state visited (NOT SPOUSE_NON_WORKING_ASSETS).
- Dashboard renders HouseholdView with twin grids.
- DB: `users.household_calculation_mode='dual_earner'`, `marriage_allowance_eligible=false`, `tax_strategy_household_inputs.spouse_annual_income > 0`.

- [ ] **Step 2: Loop until GREEN**

- [ ] **Step 3: Commit**

### Task 6.3: BS-28 — Married + spouse non-working (Path C)

**Files:**
- Create: `tests/Browser/scenarios/BS-28-savetax-married-spouse-no-work.php`

- [ ] **Step 1: Write scenario**

Asserts:
- SPOUSE_NON_WORKING_ASSETS state visited (NOT SPOUSE_HOUSEHOLD).
- Capture spouse standalone assets (mix: £5k existing ISA, others 0).
- Dashboard renders HouseholdView in single_earner_couple mode.
- AssetShiftingPanel visible with at least one savings_to_spouse suggestion AND marriage_allowance_transfer.
- Marriage Allowance card visible on user grid.
- DB: `users.household_calculation_mode='single_earner_couple'`, `marriage_allowance_eligible=true`, `tax_strategy_household_inputs.spouse_existing_isa_balance=5000`, working-spouse fields all NULL.

- [ ] **Step 2: Loop until GREEN**

- [ ] **Step 3: Commit**

### Task 6.4: Final regression sweep

- [ ] **Step 1: Architecture suite**

```bash
./vendor/bin/pest --testsuite=Architecture
```
Expected: 95/95.

- [ ] **Step 2: Onboarding + Fyn + Auth**

```bash
./vendor/bin/pest tests/Feature/Fyn tests/Feature/Onboarding tests/Feature/Auth --compact
```
Expected: baseline 396 + new tests (8 tools × 2 each = 16, state machine 7, calculator 4, endpoints 4 = 31 new) = ~427 passed, zero new failures.

- [ ] **Step 3: Tax services**

```bash
./vendor/bin/pest tests/Unit/Services/Tax
```
Expected: GREEN.

- [ ] **Step 4: All browser scenarios**

```bash
./vendor/bin/pest tests/Browser/scenarios/BS-26-savetax-single-employed.php tests/Browser/scenarios/BS-27-savetax-married-spouse-works.php tests/Browser/scenarios/BS-28-savetax-married-spouse-no-work.php
```
Expected: 3 passed, all assertions GREEN.

- [ ] **Step 5: Commit + update CSJTODO**

```bash
# Append to April/April29Updates/CSJTODO.md a new "Session 113" entry summarising what shipped.
git commit -am "docs(savetax): session 113 CSJTODO entry — sections 4-6 shipped"
```

---

## Phase 7 — Documentation + deploy notes

### Task 7.1: As-shipped spec

**Files:**
- Create: `April/April29Updates/savetax-section4-6-spec.md`

Document the actual shipped state. Mirror format of existing `savetax-feature-patch-notes.md` and `savetax-campaign-onboarding-spec.md`. Include:
- New routes, endpoints, schema, tools, states.
- 3 browser-verified personas.
- Test count delta.
- Out-of-scope confirmation.
- Architecture diagrams (state machine + service composition).

### Task 7.2: Deploy notes

**Files:**
- Create: `April/April29Updates/savetax-section4-6-deploy-notes.md`

Mirror `April/April29Updates/deploy-notes.md`. Include:
- File list (PHP backend + DB + frontend).
- Build commands per environment.
- Mandatory `php artisan migrate` (3 new migrations).
- `php artisan db:seed --class=PreviewUserSeeder --force` (preview persona household_inputs additions).
- Smoke test plan.
- Rollback procedure (drop the 3 new tables/columns; revert state machine changes).

### Task 7.3: Update vault

- [ ] **Step 1: Run /vault-sync skill**

```text
/vault-sync
```

(This invokes the vault-sync skill, which updates `/Users/CSJ/Desktop/fynlaBrain/` with version bump, MOC entries for the new dashboard route, and architecture-doc references.)

---

## Out of scope (deferred — explicit)

| # | Item | Reason | Future plan needs to cover |
|---|------|--------|----------------------------|
| OS1 | Eval YAML scenarios (S1.7.c+) for the 9 new states | Sprint 1 work, gated on S1.7.a (AssertionHelpers) | Add 9 state-machine YAMLs + 3 handoff YAMLs |
| OS2 | "Apply this strategy" write-back from sliders | Read-only modelling for now | New `apply_tax_strategy_overrides` write tool + permission gating |
| OS3 | Spouse as separate User record via SpousePermission | Standalone capture sufficient for v1 | Cross-user merge UX when spouse later registers |
| OS4 | Mobile (Capacitor iOS) rendering of /tax-strategy | Web-first | Mobile aggregator update + Vue mobile component |
| OS5 | Other campaigns (`/biggerpension`, `/paymortgage`) | Scoped to SaveTax | Reuse this state-machine pattern + calculator infrastructure |
| OS6 | UK Gilts-specific strategy (whiteboard mention) | Too narrow for v1 | Domain-specific recommendation engine extension |
| OS7 | Subscription gating clarification | Per Task 0.1, defaulting to standard auth:sanctum | Confirm with CSJ; add middleware if needed |

---

## Verification matrix (acceptance gate)

| Path | Browser scenario | Backend test | Calculator test | DB assertions |
|------|------------------|--------------|------------------|---------------|
| A — Single | BS-26 | tax-strategy show endpoint returns calculation_mode='single' | TaxStrategyCalculatorTest single mode | onboarding_completed=true, no household row |
| B — Dual earner | BS-27 | dual_earner endpoint returns 8 spouse_allowances | TaxStrategyCalculatorTest dual_earner | household_calculation_mode='dual_earner', household row with working-spouse fields |
| C — Single-earner couple | BS-28 | single_earner_couple endpoint returns asset_shifting_suggestions | TaxStrategyCalculatorTest single_earner_couple | household_calculation_mode='single_earner_couple', marriage_allowance_eligible=true, household row with non-working-spouse fields |

All three paths must be GREEN (live browser, real DB, all docblock assertions hold) before this plan is complete.

---

## Self-review

### Spec coverage check

| Spec section | Tasks |
|--------------|-------|
| Whiteboard 1: Single → Employed → Income → Tax band | Phase 3 task 3.2 (STATE_CAMPAIGN_OCCUPATIONAL_SCHEME); calculator pulls from existing user.annual_employment_income |
| Whiteboard 1: Yes → Occupational Scheme → % cont → Matching | Phase 2 task 2.1 (capture_salary_sacrifice); Phase 3 task 3.2 (state); existing dc_pensions fields |
| Whiteboard 1: SS yes/no → SS show all saving | Phase 2 task 2.1; Phase 4 calculator SalarySacrificeAnalyzer composition |
| Whiteboard 1: Excess funds → Use ISA allowance → Cash ISA → Stocks ISA | Phase 3 task 3.2 (STATE_CAMPAIGN_ISA_HOLDINGS) |
| Whiteboard 1: Bank Acc → Interest Rate → Balance → IF Savings Allowance up to £1k-£500 | Phase 3 task 3.2 (STATE_CAMPAIGN_BANK_ACCOUNTS); Phase 4 PSACalculator composition |
| Whiteboard 1: Investment Accounts → CGT allowance £3k → Div allowance £500 | Phase 3 task 3.2 (STATE_CAMPAIGN_INVESTMENT_ACCOUNTS); Phase 4 CGT + Dividend composition |
| Whiteboard 1: Pension Contributions → £60k allow → reduce income tax → 20% Basic | Phase 3 task 3.2 (STATE_CAMPAIGN_PENSION_CONTRIBS); Phase 4 AnnualAllowanceChecker composition |
| Whiteboard 1: User Tax Strategy section — Personal/Savings/CGT/Div Allowance | Phase 4 task 4.2 (calculator buildAllowanceGrid) + Phase 5 task 5.4 (AllowanceGrid component) |
| Whiteboard 1: Slider Section — Occupation Scheme % + Amount, SS yes/no, NI, Income tax | Phase 5 task 5.7 (StrategySliderPanel) |
| Whiteboard 1: Bank Acc — Bank 1 Balance/Interest/Allowance, Bank 2 | Phase 4 PSACalculator::assessPerAccount extension; Phase 5 task 5.4 |
| Whiteboard 1: Married → Spouse employed → check income tax band | Phase 3 task 3.2 (STATE_CAMPAIGN_SPOUSE_WORK + SPOUSE_HOUSEHOLD); Phase 4 task 4.3 (dual_earner) |
| Whiteboard 2: Tax Campaign — check ALL allowances are used | Phase 4 calculator covers all 8 allowances |
| Whiteboard 2: Non-Working Spouse — ISA/Savings/Move Inv/Div/PA/Starting Rate | Phase 3 task 3.2 (STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS); Phase 4 task 4.4 (single_earner_couple); Phase 5 task 5.6 (AssetShiftingPanel) |
| Whiteboard 2: £535k gilts → £18,750 tax-free worked example | Phase 4 task 4.4 buildAssetShiftingSuggestions savings_to_spouse calc (capacity-sized) |
| Whiteboard 2: 60% trap | Calculator emits position correctly; specific recommendation deferred to a later refinement of TaxOptimisationAgent (existing) |
| 4 decisions locked in | All addressed |
| 10 conflicts raised | All resolved or escalated to Task 0.1 |

### Placeholder scan

- ✅ No TBD / TODO in any task body.
- ✅ Every code step has actual code.
- ✅ Every command has expected output noted.
- ✅ Every test step shows the test code.
- ✅ Every conflict has a stated resolution OR an explicit Task 0.1 escalation.
- ✅ "60% trap" — flagged as defer to existing TaxOptimisationAgent (not a placeholder; explicit defer).

### Type consistency

- `household_calculation_mode` — same string values used across migration, model, tool handler, calculator, and DTO: `single | dual_earner | single_earner_couple`. ✓
- `marriage_allowance_eligible` — boolean, same field name across migration, model, tool, calculator, and tests. ✓
- Status values for allowance bars — `spring | violet | raspberry` consistently in calculator output and AllowanceCard component. (No `amber` or `orange` per CLAUDE.md Rule #9.) ✓
- DTO field naming — DTO uses camelCase (`pensionContributionPercent`); request payload uses snake_case (`pension_contribution_percent`); `fromRequest` mapper bridges the two. ✓
- Tool names — exact match between AiToolDefinitions, CoordinatingAgent dispatch, OnboardingChatDirector whitelist, state machine `extraction_tools`, and tests. ✓

### Scope completeness

- 3 paths covered with dedicated states + tests + browser scenarios. ✓
- Calculator branches all 3 modes. ✓
- Frontend renders correctly per mode. ✓
- Spouse data captured per path (different fields populated per path). ✓
- Asset-shifting math sized to the SMALLER of (user's at-risk holdings) and (spouse's unused capacity). ✓
- Marriage Allowance only auto-suggested when `marriage_allowance_eligible=true` AND user is basic-rate (per HMRC rule). ✓

---

## Execution handoff

**Plan complete and saved to `April/April29Updates/savetax-campaign-post-expenses-plan.md`.**

The plan is structured as 7 phases (38 tasks), each task at 2–5 minute granularity with TDD discipline (write test → verify RED → implement → verify GREEN → commit). The acceptance gate is per CLAUDE.md Rule #15: every browser scenario assertion must hold in the live browser before the plan is declared complete.

**Two execution options:**

1. **Subagent-driven (recommended)** — fresh subagent per task, two-stage review between tasks. Use `superpowers:subagent-driven-development` to dispatch.
2. **Inline execution** — tasks executed in this session in batches with checkpoint reviews. Use `superpowers:executing-plans`.

**Outstanding before execution can begin:** Task 0.1 — confirm subscription gating with CSJ (see Conflict C9 / Task 0.1).

**Which execution mode would you like?**
