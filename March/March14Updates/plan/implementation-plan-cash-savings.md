# Cash & Savings Decision Engine: Implementation Plan

> Gap analysis between `research-cash-savings.md` and the current codebase, with phased implementation to build the full 14-phase savings recommendation engine.
>
> **Date:** 2026-03-14 | **Based on:** 4 codebase audits + research document comparison

---

## Executive Summary

The current savings module has **basic infrastructure** (5 services, 1 agent, CRUD, ISA tracking) but **lacks a structured decision engine**. The Investment module uses a proven **database-driven action definition pattern** (`InvestmentActionDefinition` model + `InvestmentActionDefinitionService` with 24 configurable triggers). The Savings module must replicate this pattern — not reinvent it.

### Critical Finding

The research document describes 14 engine phases with specialised services (DataReadinessService, TaxEfficiencyAnalysisService, etc.). However, the **actual** Investment engine in the codebase does NOT use this multi-service pipeline pattern. It uses a **database-driven evaluation service** (`InvestmentActionDefinitionService`) orchestrated by `InvestmentPlanService` (which coordinates analysis from multiple agents and services) with database-driven trigger definitions. The Savings engine MUST follow the same pattern to avoid architectural divergence.

### What Exists vs What's Needed

| Area | Current State | Target State | Gap |
|------|--------------|-------------|-----|
| **Recommendation engine** | Hardcoded inline logic (~60 lines in SavingsAgent) | Database-driven triggers via SavingsActionDefinitionService | **Complete rebuild** |
| **PSA values** | Hardcoded in 3+ files | Centralised in TaxConfigService | **Migration needed** |
| **Emergency fund targets** | Hardcoded 6 months | Employment-status-based (3/6/9 months) | **Enhancement** |
| **FSCS protection** | Not implemented | Full institution grouping + breach detection | **New feature** |
| **Rate alerts** | RateComparator compares but doesn't alert | Dashboard + email notifications at 90/30/7 days | **New feature** |
| **Goal-account linking** | One-to-one via `linked_savings_account_id` | Many-to-many with priority-based allocation | **Schema change** |
| **Tax efficiency (PSA/ISA)** | ISATracker exists, no PSA analysis | Full PSA breach detection + ISA strategy | **Enhancement** |
| **Children's savings** | Junior ISA beneficiary fields exist | Per-child named recommendations | **Enhancement** |
| **Savings notifications** | None exist | 4 notification types (maturity, rate, ISA, emergency) | **New feature** |
| **Market rate source** | SavingsMarketRate table (separate from TaxConfigService) | Accessed via RateComparator (existing) — no change needed | **None** |
| **Offset mortgage** | Not implemented | Offset vs savings rate comparison | **New feature** |
| **Debt comparison** | Not implemented | Savings rate vs debt rate analysis | **New feature** |
| **Cash vs investment** | Not implemented (Investment engine does some) | Cascading waterfall: ISA → Pension → Bond → GIA | **New feature** |
| **Spouse optimisation** | Not implemented | PSA shifting, ISA coordination | **New feature** |
| **What Drives This** | Basic Recommendations.vue (cards only) | Full decision path display with missing data links | **Major UI upgrade** |
| **CashAccount model** | Exists but disconnected | Decision: merge into SavingsAccount or remove | **Cleanup needed** |

---

## Architecture Decision: Follow the Investment Pattern

**The Savings engine will use the same architecture as Investment:**

```
SavingsActionDefinition (DB model)        ← Configurable triggers + templates
        |
SavingsActionDefinitionService            ← Evaluates triggers against user data
        |
SavingsAgent::generateRecommendations()   ← Delegates to service (not inline)
        |
SavingsPlanService::getRecommendations()  ← Full pipeline with cross-module data
        |
CoordinatingAgent                         ← Cross-module conflict resolution
```

**Why this pattern, not the 14-phase pipeline from the research doc:**
1. The Investment module already uses this pattern — consistency prevents architectural divergence
2. Database-driven triggers are configurable without code changes
3. Template rendering with `{placeholder}` tokens is already proven
4. Investment currently evaluates 7 savings-related triggers (4 labelled + 3 surplus waterfall). These must be disabled when the Savings engine goes live.
5. CoordinatingAgent already handles conflict resolution

**The 14 phases from the research doc become trigger categories within the single service**, not separate service classes. This avoids 14 new service files that would duplicate the proven pattern.

---

## Implementation Phases

### Phase 0: Foundation — TaxConfigService & PSA Migration (Pre-requisite)
**Priority: CRITICAL — blocks everything else**
**Estimated scope: 2 files modified, 1 seeder updated**

#### 0.1 Add PSA to TaxConfigService

**File:** `database/seeders/TaxConfigurationSeeder.php`

Add to `income_tax` section of 2025/26 config:
```php
'personal_savings_allowance' => [
    'basic' => 1000,
    'higher' => 500,
    'additional' => 0,
],
'starting_rate_for_savings' => [
    'band' => 5000,
    'rate' => 0,
],
```

**File:** `app/Services/TaxConfigService.php`

Add helper method:
```php
public function getPersonalSavingsAllowance(?string $taxBand = null): int|array
```

#### 0.2 Remove PSA Hardcoding

Replace hardcoded PSA values in ALL locations:

| File | Line(s) | Current | Replace With |
|------|---------|---------|-------------|
| `app/Services/UKTaxCalculator.php` | 673-677 | `1000/500/0` | `$this->taxConfig->getPersonalSavingsAllowance($band)` |
| `app/Services/Tax/TaxOptimisationService.php` | 217-220 | `1000/500/0` | `$this->taxConfig->getPersonalSavingsAllowance($band)` |
| `app/Services/Tax/TaxProductInfoService.php` | 150-151 | `1000/500` | `$this->taxConfig->getPersonalSavingsAllowance($band)` |
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` | Various | Hardcoded | `$this->taxConfig->getPersonalSavingsAllowance($band)` |
| `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` | Various | Hardcoded | `$this->taxConfig->getPersonalSavingsAllowance($band)` |
| `app/Services/Investment/AssetLocation/TaxDragCalculator.php` | Various | Hardcoded | `$this->taxConfig->getPersonalSavingsAllowance($band)` |

**Note:** `TaxDragCalculator` derives PSA from `$incomeTaxRate` (a float), not a band string. Migration requires converting rate to band (`rate <= 0.20 → 'basic'`, `rate <= 0.40 → 'higher'`, else `'additional'`) before calling `getPersonalSavingsAllowance()`.

#### 0.3 Add FSCS Constants to TaxConfigService

Add to seeder under new `savings` section:
```php
'savings' => [
    'fscs_deposit_protection' => 85000,
    'fscs_joint_protection' => 170000,
    'fscs_temporary_high_balance' => 1000000,
    'fscs_temporary_high_balance_months' => 6,
    'premium_bonds_max_holding' => 50000,
    'premium_bonds_min_purchase' => 25,
    'premium_bonds_min_age_self' => 16,
    'premium_bonds_prize_fund_rate' => 0.044,
    'parental_settlement_threshold' => 100,
],
```

Add helper method:
```php
public function getSavingsConfig(?string $key = null): mixed
```

**Tests:** Update existing PSA-related tests to use TaxConfigService values.

---

### Phase 1: Database Schema — Action Definitions & Goal-Account Pivot
**Priority: HIGH — foundation for engine**
**Estimated scope: 2 migrations, 2 models, 1 seeder**

#### 1.1 Create SavingsActionDefinition Model + Migration

**Migration:** `create_savings_action_definitions_table`

Mirror `investment_action_definitions` exactly:
```
id, key (unique), source (agent|goal), title_template, description_template,
action_template, category, priority (enum), scope (account|portfolio),
what_if_impact_type, trigger_config (json), is_enabled (bool),
sort_order, notes, timestamps
```

**Model:** `app/Models/SavingsActionDefinition.php`
- Same methods as `InvestmentActionDefinition` (renderTitle, renderDescription, renderAction, scopes)

#### 1.2 Create Goal-Account Pivot Table

**Migration:** `create_goal_savings_account_table`

```
id, goal_id (FK), savings_account_id (FK), allocated_amount (decimal:15,2),
is_primary (bool), priority_rank (int), timestamps
unique: [goal_id, savings_account_id]
```

Update `Goal` model:
```php
public function savingsAccounts(): BelongsToMany {
    return $this->belongsToMany(SavingsAccount::class, 'goal_savings_account')
        ->withPivot('allocated_amount', 'is_primary', 'priority_rank')
        ->withTimestamps();
}
```

Update `SavingsAccount` model:
```php
public function goals(): BelongsToMany {
    return $this->belongsToMany(Goal::class, 'goal_savings_account')
        ->withPivot('allocated_amount', 'is_primary', 'priority_rank')
        ->withTimestamps();
}
```

**Data migration:** Move existing `linked_savings_account_id` values into pivot table, then deprecate the column (do NOT remove yet — backwards compatibility).

**Observer Update (MANDATORY before deprecating FK):**

Update `SavingsAccountGoalObserver` to query the new pivot table instead of `linked_savings_account_id`:
```php
// BEFORE: Goal::where('linked_savings_account_id', $account->id)
// AFTER: Goal::whereHas('savingsAccounts', fn($q) => $q->where('savings_account_id', $account->id))
```

Update `TracksGoalContributions` trait similarly.

Audit `linked_account_ids` (JSON array) on the Goal model. If unused for savings goals, document it as investment-goal-specific in a model docblock. Do NOT leave three linking mechanisms in production simultaneously.

#### 1.3 Seed Savings Action Definitions

**Seeder:** `database/seeders/SavingsActionDefinitionSeeder.php`

Define triggers across these categories (mapped from research doc phases):

**Data Readiness (Phase 1 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `missing_date_of_birth` | `user.date_of_birth IS NULL` | critical |
| `missing_income` | `gross_annual_income <= 0` | critical |
| `missing_expenditure` | `monthly_expenditure IS NULL OR <= 0` | critical |
| `missing_employment_status` | `employment_status IS NULL` | medium |

**Emergency Fund (Phase 2 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `emergency_fund_critical` | `runway < 1 month` | critical |
| `emergency_fund_low` | `runway 1-3 months` | high |
| `emergency_fund_building` | `runway 3 months to target` | medium |
| `emergency_fund_excess` | `runway > target + 3 months` | low |
| `emergency_fund_no_data` | `expenditure missing` | high |

**Tax Efficiency (Phase 3 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `psa_breached` | `annual_interest > PSA` | high |
| `psa_approaching` | `annual_interest > PSA * 0.75` | medium |
| `psa_additional_rate` | `tax_band = additional AND has non-ISA savings` | critical |
| `starting_rate_unused` | `income < PA + 5000 AND savings_interest < 5000` | medium |
| `cash_isa_recommended` | `PSA breached AND ISA allowance remaining` | high |
| `cash_isa_not_needed` | `basic rate AND PSA comfortable` | info |

**Rate Optimisation (Phase 4 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `rate_below_market` | `account_rate < market_rate - 0.5%` | medium |
| `rate_poor` | `account_rate < market_rate - 1.0%` | high |
| `fixed_maturity_warning` | `maturity_date within 90 days` | medium |
| `fixed_maturity_urgent` | `maturity_date within 30 days` | high |
| `promo_rate_expiring` | `rate_valid_until within 90 days` | high |
| `regular_saver_opportunity` | `disposable > 0 AND no regular_saver AND best_regular > easy_access + 1%` | medium |

**FSCS Protection (Phase 6 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `fscs_breach` | `per-institution total > 85000` | high |
| `fscs_approaching` | `per-institution total > 75000` | medium |

**Debt vs Savings (Phase 7 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `debt_rate_exceeds_savings` | `any debt rate > best savings rate` | high |
| `offset_mortgage_better` | `mortgage_rate > after_tax_savings_rate` | medium |

**Cash vs Investment (Phase 8 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `excess_cash_isa_available` | `excess > 1000 AND ISA remaining` | high |
| `excess_cash_pension` | `excess > 1000 AND ISA exhausted AND pension AA remaining` | high |
| `excess_cash_bond` | `excess > 5000 AND ISA + pension exhausted AND higher/additional rate` | medium |
| `excess_cash_gia` | `excess > 1000 AND all wrappers exhausted` | low |

**Goal-Linked (Phase 9 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `goal_wrong_account_type` | `goal timeline vs account access_type mismatch` | medium |
| `goal_off_track` | `required_monthly > current contribution` | high |
| `goal_nearly_achieved` | `progress > 90%` | info |
| `goal_no_linked_account` | `goal exists with no linked account` | medium |
| `goal_multi_account_rebalance` | `account shared by multiple goals, priority conflict` | medium |

**Children's Savings (Phase 10 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `child_no_jisa` | `child under 18, no Junior ISA` | medium |
| `child_jisa_allowance_remaining` | `JISA contributions < 9000` | info |
| `child_jisa_cash_vs_ss` | `child age < 10 AND cash JISA held` | low |
| `child_parental_settlement` | `parent gift interest > 100/yr` | high |
| `child_turning_18` | `child turning 18 within 12 months` | medium |

**Spouse Optimisation (Phase 11 equivalent):**
| Key | Trigger | Priority |
|-----|---------|----------|
| `spouse_psa_shift` | `different tax bands AND lower-rate spouse has PSA headroom` | medium |
| `spouse_isa_coordination` | `one partner ISA exhausted, other has remaining` | medium |

**Total: ~40 action definitions** (vs Investment's 24)

---

### Phase 2: Core Engine — SavingsActionDefinitionService
**Priority: HIGH — the engine itself**
**Estimated scope: 1 large service file (~800 lines)**

#### 2.1 Create SavingsActionDefinitionService

**File:** `app/Services/Savings/SavingsActionDefinitionService.php`

Follow `InvestmentActionDefinitionService` pattern exactly:

```php
class SavingsActionDefinitionService
{
    public function evaluateAgentActions(
        array $savingsAnalysis,
        array $investmentAnalysis,
        Collection $savingsAccounts,
        Collection $investmentAccounts,
        int $userId
    ): array

    // Private evaluators — one per trigger key
    private function evaluateEmergencyFundCritical(...): array
    private function evaluatePsaBreached(...): array
    private function evaluateFscsBreach(...): array
    // ... etc (~40 methods)

    private function resolveConflicts(array $recommendations): array
}
```

**Key design decisions:**
- Each evaluator returns `[]` (not triggered) or `[recommendation]` (triggered)
- Account-scoped triggers iterate accounts and may return multiple recommendations
- All tax values fetched from `TaxConfigService` — never hardcoded
- User's tax band fetched from database (pre-calculated by Income module)
- Template variables resolved via `SavingsActionDefinition::renderTitle($vars)`

#### 2.2 Enhance Existing Services

**EmergencyFundCalculator** — Add employment-based targets:
```php
public function getTargetMonths(string $employmentStatus): int
// employed: 6, self_employed: 9, unemployed: 6, retired: 3, contractor: 9
```

Currently hardcoded to 6 months. Add a `getTargetMonths()` method that reads employment status.

**Divergence with Investment engine:** `InvestmentActionDefinitionService::calculateSurplus()` uses `PlanConfigService::getEmergencyFundTargetMonths()` which always returns 6 months (no employment-status awareness). The Savings engine will use employment-based targets. This is intentional — the Investment surplus waterfall uses a universal 6-month baseline for simplicity, while the Savings engine provides the detailed employment-specific analysis. Document this divergence explicitly so developers understand it is by design, not an oversight.

**RateComparator** — Add institution grouping for FSCS:
```php
public function getInstitutionExposure(Collection $accounts): array
// Groups accounts by banking licence, calculates per-institution totals
```

**ISATracker** — No changes needed (already well-integrated with TaxConfigService).

**LiquidityAnalyzer** — Add FSCS overlay to liquidity summary.

#### 2.3 Create PSA Calculator

**File:** `app/Services/Savings/PSACalculator.php`

```php
class PSACalculator
{
    public function assessPSAPosition(int $userId): array
    // Returns: tax_band (from DB), psa_amount (from TaxConfigService),
    //          annual_interest, breach_amount, headroom, utilisation_percent

    public function calculateAnnualInterest(Collection $accounts): float
    // Sums balance * rate for non-ISA accounts only
}
```

**Does NOT recalculate tax band** — reads from database (Income module is source of truth).

#### 2.4 Create FSCS Assessor

**File:** `app/Services/Savings/FSCSAssessor.php`

```php
class FSCSAssessor
{
    public function assessExposure(Collection $accounts): array
    // Groups by institution (banking licence groups stored in config)
    // Returns per-institution totals + breach status

    public function getBankingLicenceGroups(): array
    // Returns known shared-licence groups (Lloyds/Halifax/BoS, etc.)
}
```

Banking licence groups stored in config file (not TaxConfigService — these are institution data, not tax data):
```php
// config/banking_licence_groups.php
```

#### 2.5 Disable Investment Engine Savings Triggers (LAUNCH GATE)

**This is a launch gate — the Savings engine MUST NOT go live until all 7 Investment engine savings triggers are disabled. This MUST happen in the same seeder run that enables the Savings engine triggers.**

The Investment engine currently evaluates 7 savings-related triggers via `InvestmentActionDefinitionService`. When the Savings engine goes live, these MUST be set to `is_enabled = false` in `InvestmentActionDefinitionSeeder` (or a dedicated migration) to prevent duplicate recommendations:

| # | Trigger Key | Current Evaluator | Overlap With Savings Engine |
|---|-------------|-------------------|-----------------------------|
| 1 | `emergency_runway_below` | `evaluateEmergencyFundCritical()` | `emergency_fund_critical`, `emergency_fund_low` |
| 2 | `emergency_runway_between` | `evaluateEmergencyFundGrow()` | `emergency_fund_building` |
| 3 | `has_poor_rate_accounts` | `evaluateSwitchSavingsRate()` | `rate_below_market`, `rate_poor` |
| 4 | `isa_remaining_and_runway_above` | `evaluateIsaAllowanceRemaining()` | `cash_isa_recommended` |
| 5 | `surplus_exists_and_isa_remaining` | `evaluateSurplusToIsa()` | `excess_cash_isa_available` |
| 6 | `surplus_exceeds_isa` | `evaluateSurplusToPension()` | `excess_cash_pension` |
| 7 | `surplus_exceeds_pension` | `evaluateSurplusToBond()` | `excess_cash_bond` |

**Implementation:** Add to `SavingsActionDefinitionSeeder::run()`:
```php
// Disable Investment engine savings triggers (replaced by Savings engine)
\App\Models\InvestmentActionDefinition::whereIn('key', [
    'emergency_runway_below',
    'emergency_runway_between',
    'has_poor_rate_accounts',
    'isa_remaining_and_runway_above',
    'surplus_exists_and_isa_remaining',
    'surplus_exceeds_isa',
    'surplus_exceeds_pension',
])->update(['is_enabled' => false]);
```

**Verification:** After seeding, confirm all 7 triggers show `is_enabled = false` in `investment_action_definitions` table.

---

### Phase 3: Agent & Controller Refactor
**Priority: HIGH — connects engine to API**
**Estimated scope: 2 files modified**

**Observer compatibility:** `SavingsAccountRiskObserver` (triggers risk recalculation on emergency fund balance changes) is unaffected by these changes — it reads from raw model data, not agent analysis. Verified: no amendment needed.

**Deployment:** After deploying Phase 3, run `php artisan cache:clear` to invalidate stale analysis caches (30-minute TTL). Mobile dashboard cache (`mobile_dashboard_*`) will also be cleared. Per project conventions, cache must always be cleared after backend changes.

#### 3.1 Refactor SavingsAgent

**File:** `app/Agents/SavingsAgent.php`

**Change:** Replace `generateRecommendations()` inline logic with delegation:

```php
public function generateRecommendations(array $analysisData): array
{
    return $this->actionDefinitionService->evaluateAgentActions(
        $analysisData['savings'],
        $analysisData['investment'] ?? [],
        $this->getSavingsAccounts($analysisData['user_id']),
        $this->getInvestmentAccounts($analysisData['user_id']),
        $analysisData['user_id']
    );
}
```

**Enhance:** `analyze()` method to include new data:
- PSA position (via PSACalculator)
- FSCS exposure (via FSCSAssessor)
- Employment-based emergency fund target
- Per-child Junior ISA status

#### 3.2 Create SavingsPlanService

**File:** `app/Services/Plans/SavingsPlanService.php`

Mirror `InvestmentPlanService`:
```php
public function getRecommendations(int $userId, ?array $preComputedData = null): array
```

Full pipeline: savings analysis + investment analysis + account collections + evaluate all triggers + merge + resolve conflicts.

#### 3.3 Update SavingsController

**File:** `app/Http/Controllers/Api/SavingsController.php`

Update `recommendations()` to use `SavingsPlanService` for the full evaluation path.

Update `index()` response to include:
- PSA position
- FSCS exposure summary
- Employment-based emergency fund target
- Per-child savings status with `{child_name}` variables

**CoordinatingAgent compatibility:** Verify `PriorityRanker::rankRecommendations()` can extract urgency from the new recommendation shape (uses `impact` field, not `urgency_score`). Add field mapping in `CoordinatingAgent::mapSavingsAnalysis()` if needed.

---

### Phase 4: Notification System
**Priority: MEDIUM — dashboard alerts**
**Estimated scope: 4 notification classes, 1 command, 1 migration**

#### 4.1 Create Notification Classes

| Class | Trigger | Channels |
|-------|---------|----------|
| `SavingsMaturityAlertNotification` | Fixed-rate maturity approaching | database |
| `SavingsRateExpiryNotification` | Promotional rate expiring | database |
| `ISAAllowanceWarningNotification` | ISA allowance year-end | database |
| `EmergencyFundAlertNotification` | Emergency fund critical | database |

Notifications follow the established project pattern — `database` channel only, delivered via `PushNotificationService::sendToUser()` for mobile push. Email delivery is not currently implemented for alert-type notifications and would require separate infrastructure work.

#### 4.2 Update NotificationPreference Model

Add columns:
```php
'savings_maturity_alerts' => true,
'savings_rate_alerts' => true,
'isa_allowance_warnings' => true,
```

#### 4.3 Create Scheduled Command

**File:** `app/Console/Commands/SendSavingsAlerts.php`

Mirror `SendMortgageRateAlerts` pattern using `PushNotificationService`.

Runs daily. Checks:
- Fixed-rate accounts maturing within 90/30/7 days
- Promotional rates expiring within 90/30/7 days
- ISA allowance remaining + less than 3 months to April 5

Creates `database` notifications and delivers via `PushNotificationService::sendToUser()` for mobile push (respects user notification preferences).

#### 4.4 Register in Kernel

Add to `app/Console/Kernel.php` schedule:
```php
$schedule->command('savings:send-alerts')->daily();
```

---

### Phase 5: Frontend — What Drives This & Decision Display
**Priority: MEDIUM — user-facing value**
**Estimated scope: 3-4 new components, 2 modified**

#### 5.1 Enhance Recommendations.vue

Replace basic card display with structured recommendation cards showing:
- Priority badge (existing)
- Headline (from `title_template`)
- Description with user's actual numbers (from `description_template` rendered)
- **Decision path** — collapsible section showing which data points drove this recommendation
- **Action link** — links to the relevant input form if data is missing
- **"What Drives This"** section for missing data items

#### 5.2 Create SavingsDecisionPath.vue

New component showing the decision trail:
```
We checked: Do you have an emergency fund? → Yes, 2.3 months
We checked: Is that enough? → No, you need 6 months (employed)
Outcome: Build your emergency fund to 6 months of expenses
```

#### 5.3 Create MissingDataCard.vue

Clickable card for "What Drives This" missing data:
- What information is missing
- What analysis it would unlock
- Click → navigates to the input form for that data

#### 5.4 Update Vuex savings.js Store

Add to state:
```javascript
psaPosition: null,
fscsExposure: null,
decisionPaths: [],
missingData: []
```

Add actions to fetch the enhanced analysis data.

---

### Phase 6: Cleanup & Integration
**Priority: LOW — technical debt prevention**
**Estimated scope: varies**

#### 6.1 CashAccount Model Decision

**Recommendation: Do NOT merge CashAccount into SavingsAccount.**

CashAccount appears to serve a different purpose (household cash flow tracking, current accounts). Keep it separate but ensure it is NOT included in savings recommendation analysis to avoid double-counting.

Add a comment to CashAccount model:
```php
/**
 * CashAccount tracks current/transactional accounts for cash flow analysis.
 * It is NOT part of the savings recommendation engine.
 * Savings accounts are managed via SavingsAccount model.
 */
```

#### 6.2 Legacy SavingsGoal Deprecation

- Keep deprecated endpoints working (backwards compatibility)
- Add migration banner in SavingsGoals.vue (already exists) pointing to Goals module
- Remove legacy goal CRUD from SavingsController in a future version (not now)

#### 6.3 Cross-Module Integration

**Moved to Phase 2.5 (LAUNCH GATE).** All 7 Investment engine savings triggers are disabled as part of the Savings engine enablement in Phase 2.5. No further cross-module trigger work is needed here. Any future cross-module coordination (e.g., Savings engine reading Investment data) is handled by `CoordinatingAgent` (see 6.4).

#### 6.4 CoordinatingAgent Integration

Ensure `CoordinatingAgent` includes Savings recommendations in:
- Conflict resolution (e.g., emergency fund vs pension contribution)
- Priority ranking (across all modules)
- Surplus allocation (emergency fund is highest priority)

---

## Dependency Graph

```
Phase 0 ─────────────────────────────────────────────────┐
(TaxConfigService + PSA migration)                       |
    |                                                     |
    v                                                     |
Phase 1 ─────────────────────────────────────────────────┤
(DB schema: action definitions + goal-account pivot      |
 + observer update for pivot table)                      |
    |                                                     |
    v                                                     |
Phase 2 ─────────────────────────────────────────────────┤
(Core engine: SavingsActionDefinitionService)            |
    |                                                     |
    v                                                     |
Phase 2.5 ── LAUNCH GATE ───────────────────────────────┤
(Disable 7 Investment engine savings triggers)           |
    |                                                     |
    v                                                     |
Phase 3 ─────────────────────────────────────────────────┤
(Agent + controller refactor)                            |
    |                     \                               |
    v                      v                              |
Phase 4                Phase 5                            |
(Notifications)        (Frontend UI)                      |
    |                      |                              |
    v                      v                              |
Phase 6 ─────────────────────────────────────────────────┘
(Cleanup + integration)
```

Phases 4 and 5 can run in parallel. Phase 2.5 is a mandatory launch gate — the Savings engine MUST NOT go live until all 7 Investment engine savings triggers are disabled. Everything else is sequential.

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| PSA migration breaks existing tax calculations | Run full Pest test suite after Phase 0. PSA values unchanged — only the source changes. |
| Goal-account pivot migration loses data | Write data migration that copies `linked_savings_account_id` to pivot table BEFORE deprecating column. |
| 40 action definitions overwhelm users | Start with core triggers enabled, mark advanced ones as `is_enabled = false`. Enable incrementally. |
| Cross-module evaluation creates circular deps | SavingsActionDefinitionService and InvestmentActionDefinitionService must NOT call each other. CoordinatingAgent handles cross-module logic. |
| Frontend changes break mobile app | All changes are API-additive (new fields). Existing response fields remain unchanged. Mobile reads what it needs. |

---

## Files Created (New)

| File | Type | Purpose |
|------|------|---------|
| `app/Models/SavingsActionDefinition.php` | Model | Configurable trigger definitions |
| `app/Services/Savings/SavingsActionDefinitionService.php` | Service | Core evaluation engine (~800 lines) |
| `app/Services/Savings/PSACalculator.php` | Service | PSA breach detection |
| `app/Services/Savings/FSCSAssessor.php` | Service | FSCS institution exposure |
| `app/Services/Plans/SavingsPlanService.php` | Service | Full recommendation pipeline |
| `config/banking_licence_groups.php` | Config | FSCS institution groupings |
| `database/migrations/xxx_create_savings_action_definitions_table.php` | Migration | Action definitions table |
| `database/migrations/xxx_create_goal_savings_account_table.php` | Migration | Goal-account many-to-many pivot |
| `database/seeders/SavingsActionDefinitionSeeder.php` | Seeder | ~40 trigger definitions |
| `app/Notifications/SavingsMaturityAlertNotification.php` | Notification | Maturity dashboard alert |
| `app/Notifications/SavingsRateExpiryNotification.php` | Notification | Rate expiry dashboard alert |
| `app/Notifications/ISAAllowanceWarningNotification.php` | Notification | ISA year-end warning |
| `app/Notifications/EmergencyFundAlertNotification.php` | Notification | Emergency fund critical |
| `app/Console/Commands/SendSavingsAlerts.php` | Command | Daily alert scheduler |
| `resources/js/components/Savings/SavingsDecisionPath.vue` | Vue | Decision trail display |
| `resources/js/components/Savings/MissingDataCard.vue` | Vue | Missing data links |

## Files Modified (Existing)

| File | Change |
|------|--------|
| `app/Services/TaxConfigService.php` | Add `getPersonalSavingsAllowance()`, `getSavingsConfig()` |
| `database/seeders/TaxConfigurationSeeder.php` | Add PSA + savings constants to config_data |
| `app/Services/UKTaxCalculator.php` | Replace hardcoded PSA with TaxConfigService |
| `app/Services/Tax/TaxOptimisationService.php` | Replace hardcoded PSA with TaxConfigService |
| `app/Services/Tax/TaxProductInfoService.php` | Replace hardcoded PSA with TaxConfigService |
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` | Replace hardcoded PSA with TaxConfigService |
| `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` | Replace hardcoded PSA with TaxConfigService |
| `app/Services/Investment/AssetLocation/TaxDragCalculator.php` | Replace hardcoded PSA with TaxConfigService |
| `app/Agents/SavingsAgent.php` | Delegate to SavingsActionDefinitionService |
| `app/Services/Savings/EmergencyFundCalculator.php` | Add employment-based target months |
| `app/Services/Savings/RateComparator.php` | Add institution grouping |
| `app/Models/Goal.php` | Add `savingsAccounts()` BelongsToMany relationship |
| `app/Models/SavingsAccount.php` | Add `goals()` BelongsToMany relationship |
| `app/Models/NotificationPreference.php` | Add savings alert preference columns |
| `app/Observers/SavingsAccountGoalObserver.php` | Update to query pivot table instead of `linked_savings_account_id` |
| `app/Traits/TracksGoalContributions.php` | Update to query pivot table instead of `linked_savings_account_id` |
| `app/Http/Controllers/Api/SavingsController.php` | Use SavingsPlanService for recommendations |
| `resources/js/components/Savings/Recommendations.vue` | Enhanced decision display |
| `resources/js/store/modules/savings.js` | Add PSA, FSCS, decision paths to state |

---

## What This Plan Does NOT Include

1. **Offset mortgage feature** — Deferred. Requires mortgage module data integration. Add as Phase 7 later.
2. **Help to Save tracking** — Scheme closed to new applicants. Only track existing enrolments if data exists.
3. **Credit union integration** — Low priority. Track as standard savings account if user adds one.
4. **Scottish income tax rates** — PSA is the same across UK. Scottish rates only affect marginal rate calculations, which are handled by the Income module.
5. **Real-time market rate updates** — Market rates are seeded. Live rate feeds are a separate feature.
6. **Open Banking integration** — Exists as a separate feature. Not part of the decision engine.

---

## Testing Strategy

| Phase | Tests |
|-------|-------|
| Phase 0 | Run full `./vendor/bin/pest` suite. All existing PSA tests must still pass. |
| Phase 1 | Migration tests. Seeder idempotency test. Goal-account pivot data migration test. |
| Phase 2 | Unit tests for each evaluator method (~40 tests). Integration test for full pipeline. |
| Phase 3 | Feature tests for `/api/savings/recommendations` endpoint. |
| Phase 4 | Unit tests for notification classes. Feature test for scheduled command. |
| Phase 5 | Manual browser testing (components are Vue, not unit-testable in Pest). |
| Phase 6 | Architecture tests to verify no remaining hardcoded PSA values. |
