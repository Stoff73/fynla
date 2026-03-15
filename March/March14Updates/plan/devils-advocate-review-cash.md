# Devil's Advocate Review: implementation-plan-cash-savings.md

> **Date:** 2026-03-14 | **Reviewer:** Automated codebase audit
> **Method:** Direct inspection of all referenced files plus exhaustive grep across the codebase

---

## CRITICAL ISSUES (3) — Plan Must Be Amended Before Build

### CRITICAL 1: Plan Undercounts Savings Triggers in InvestmentActionDefinitionService — 7 Not 4

The plan states "the Investment service has 4 savings-related triggers." The actual count is **7**:

**4 labelled savings triggers:**
- `emergency_runway_below` → evaluateEmergencyFundCritical()
- `emergency_runway_between` → evaluateEmergencyFundGrow()
- `has_poor_rate_accounts` → evaluateSwitchSavingsRate()
- `isa_remaining_and_runway_above` → evaluateIsaAllowanceRemaining()

**3 surplus waterfall triggers (also read savingsAnalysis):**
- `surplus_exists_and_isa_remaining` → evaluateSurplusToIsa()
- `surplus_exceeds_isa` → evaluateSurplusToPension()
- `surplus_exceeds_pension` → evaluateSurplusToBond()

The proposed Savings engine includes `excess_cash_isa_available`, `excess_cash_pension`, `excess_cash_bond`, `excess_cash_gia` — these overlap directly. Unless all 7 Investment triggers are disabled when Savings goes live, users receive **duplicate recommendations**.

**Recommendation:** Phase 6.3 must be elevated to a **launch gate** (not cleanup). All 7 triggers must be explicitly listed and disabled in the same seeder run that enables the Savings engine.

---

### CRITICAL 2: getSavingsMarketRates() on TaxConfigService Is an Architectural Violation

`TaxConfigService` is a request-scoped singleton that loads ONE JSON blob from `TaxConfiguration WHERE is_active = true`. Every method reads from `Arr::get($this->config, $key)`.

`SavingsMarketRate` is a completely separate model/table. Adding `getSavingsMarketRates()` to TaxConfigService requires either (a) a second DB query breaking the single-load pattern, or (b) loading market rates into the config blob, conflating regulatory tax values with market data.

`RateComparator::getMarketBenchmarks()` already queries `SavingsMarketRate` correctly with fallback defaults.

**Recommendation:** Remove Phase 0.4 entirely. Market rates already have a correct access point in `RateComparator`. `SavingsActionDefinitionService` should receive rate data as a parameter from the orchestrator.

---

### CRITICAL 3: Pivot Table Creates a Third Goal-Account Linking Mechanism

The `Goal` model currently has **two** linking mechanisms:
1. `linked_savings_account_id` — Integer FK (used by `SavingsAccountGoalObserver`, `GoalStrategyService`, form requests, Vue frontend)
2. `linked_account_ids` — JSON array (cast to array, in `$fillable` — appears to be dead code for savings)

The proposed pivot table would be **mechanism #3**.

**Critically:** `SavingsAccountGoalObserver` auto-records contributions when savings account balance changes. It is hardcoded to use `linked_savings_account_id`:
```php
$linkedGoals = Goal::where($this->getLinkedField(), $account->id)->where('status', 'active')->get();
```

Deprecating the FK column without updating the observer causes **silent contribution tracking failure** — balance increases no longer auto-record contributions for any goals migrated to the pivot table.

**Recommendation:** Before adding a pivot table: (a) audit `linked_account_ids` — if dead code for savings, document it; (b) update `SavingsAccountGoalObserver` to query the pivot table; (c) update `TracksGoalContributions` trait. Do NOT deprecate `linked_savings_account_id` until the observer is rewritten.

---

## HIGH SEVERITY (3) — Will Cause Functional Defects

### HIGH 1: Employment-Status Emergency Fund Targets Will Diverge Between Engines

After Phase 2.2, `SavingsActionDefinitionService` will use employment-based targets (3/6/9 months). But `InvestmentActionDefinitionService::calculateSurplus()` uses `PlanConfigService::getEmergencyFundTargetMonths()` which always returns 6.

**Concrete example:** Self-employed user needs 9 months. Has 7 months saved. Savings engine: "build your emergency fund" (7 < 9). Investment engine: "you have surplus, invest it" (7 > 6). Contradictory advice.

**Recommendation:** Either extend `PlanConfigService` to be employment-aware, or document that the Investment surplus waterfall intentionally uses a universal 6-month baseline (simpler approach, defensible).

### HIGH 2: Double-Fire Risk Is Understated

Both engines evaluate the same underlying savings data via separate DB trigger rows. The plan treats migration of overlapping triggers as Phase 6 "cleanup." It must be a Phase 2 **launch gate** — atomic with the Savings engine enablement.

### HIGH 3: Notification Email Channel Conflicts With Project Pattern

All existing notification classes (`MortgageRateAlertNotification`, `ContributionReminderNotification`, `DailyInsightNotification`, etc.) use `database` channel only. `SendMortgageRateAlerts` command uses `PushNotificationService::sendToUser()`, not email.

The plan specifies `mail, database` for 3 of 4 notifications. No alert-type email template infrastructure exists in this codebase.

**Recommendation:** Change all 4 notifications to `database` channel only, consistent with established pattern. If email is genuinely required, plan it as separate work.

---

## MEDIUM SEVERITY (5)

### MEDIUM 1: "Single Flat Service" Characterisation Understates Investment Engine
`InvestmentPlanService` orchestrates `InvestmentAgent::analyze()`, `SavingsAgent::analyze()`, `FeeAnalyzer`, `InvestmentActionDefinitionService`, and `RecommendationPersonaliser`. Not truly "single." Revise framing.

### MEDIUM 2: BasePlanService Abstract Signature Mismatch
`BasePlanService` declares `getRecommendations(int $userId): array` but `InvestmentPlanService` uses `getRecommendations(int $userId, ?array $preComputedData = null): array`. The proposed `SavingsPlanService` follows Investment's pattern (correct) but the abstract should be updated.

### MEDIUM 3: Observers Not Mentioned
`SavingsAccountGoalObserver` broken by pivot migration (covered in CRITICAL 3). `SavingsAccountRiskObserver` is unaffected but should be noted as verified.

### MEDIUM 4: linked_account_ids Is Dead Code — Resolve Before Adding Pivot
Three mechanisms in production simultaneously is technical debt. Audit and document `linked_account_ids` before creating the pivot table.

### MEDIUM 5: 30-Minute Stale Cache Post-Deployment
`SavingsAgent::analyze()` caches for 1800 seconds. After Phase 3 deployment, cached responses will have old array shape. Add `php artisan cache:clear` to Phase 3 deployment notes.

---

## LOW SEVERITY (4)

### LOW 1: PSACalculator Scope Is Correct
`TaxOptimisationService::analyzePersonalSavingsAllowance()` exists but doesn't calculate interest against accounts. `PSACalculator` adds this. No duplication.

### LOW 2: FSCSAssessor Config File Placement Is Correct
Banking licence groups in `config/banking_licence_groups.php` is the right approach.

### LOW 3: TaxDragCalculator Uses Rate-Float, Not Band-String
`TaxDragCalculator` derives PSA from `$incomeTaxRate` (a float), not a band string. Migration requires rate-to-band conversion before calling `getPersonalSavingsAllowance()`.

### LOW 4: PriorityRanker May Score New Recommendation Shape as 0 Urgency
`PriorityRanker::rankRecommendations()` reads generic field names (`urgency_score`). New Savings recommendations use `impact`. Verify field mapping before Phase 3.

---

## SUMMARY

| # | Severity | Finding | Amendment |
|---|----------|---------|-----------|
| C1 | Critical | 7 triggers not 4; surplus waterfall will double-fire | Elevate Phase 6.3 to launch gate |
| C2 | Critical | getSavingsMarketRates() breaks TaxConfigService architecture | Remove Phase 0.4 |
| C3 | Critical | Pivot table is mechanism #3; observer silently breaks | Update observer before deprecating FK |
| H1 | High | Employment-based targets diverge between engines | Document or fix |
| H2 | High | Double-fire risk is a launch gate, not cleanup | Elevate Phase 6.3 |
| H3 | High | Mail channel not used by any alert notification | Change to database only |
| M1 | Medium | "Single flat service" understates architecture | Revise framing |
| M2 | Medium | BasePlanService abstract signature mismatch | Note for future |
| M3 | Medium | Observers not mentioned | Add notes |
| M4 | Medium | linked_account_ids dead code creates mechanism #3 | Audit first |
| M5 | Medium | 30-min stale cache post-deployment | Add cache:clear note |
| L1 | Low | PSACalculator scope correct | No change |
| L2 | Low | FSCSAssessor config correct | No change |
| L3 | Low | TaxDragCalculator rate-based PSA lookup | Note migration approach |
| L4 | Low | PriorityRanker field name mismatch | Verify before Phase 3 |

---

## What the Plan Gets Right (Do Not Change)

1. Following the InvestmentActionDefinition pattern (not 14 separate service classes)
2. PSA migration to TaxConfigService (except TaxDragCalculator detail)
3. FSCSAssessor in config file, not TaxConfigService
4. CashAccount NOT merged into SavingsAccount
5. SavingsPlanService as orchestrator (mirrors InvestmentPlanService)
6. Phase ordering dependency graph (except Phase 6.3 timing)
7. Goal-account pivot schema design (problem is migration sequence, not schema)
